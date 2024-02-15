<?php

namespace App\Http\Controllers\Auth;

use Exception;
use LdapRecord\Container;
use App\Mail\ResetPassword;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Models\AdPasswordReset;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Ldap\UserLdap;
use App\Utils\Helpers;
use Illuminate\Support\Facades\Mail;
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Exceptions\ConstraintViolationException;
use LdapRecord\Models\ActiveDirectory\User;
use Illuminate\Support\Str;

class SendTokenResetPasswordController extends Controller
{
	use ApiResponser;

	private $connection;

	public function __construct(Container $connection)
	{
		$this->connection = $connection::getConnection('default');
		ini_set('memory_limit', '-1');
	}

	public function getUserByCpf(Request $request)
	{
		$request->validate(
			[
				'cpf' => 'required|string|',
			],
			[
				'cpf.required' => 'O campo CPF é obrigatório para esta ação',
				'cpf.string' => 'O campo CPF precisa ser do tipo String'
			]
		);

		$cpfMasked = Helpers::formatCnpjCpf($request->cpf);

		$user = $this->connection->query()->whereIn('description', [$request->cpf, $cpfMasked])->first();

		if (!$user) {
			return $this->errorResponse("CPF Não encontrado!");
		}

		$email = $user['mail'][0];
		$emailMasked =  str::mask($email, '*', 4, 7);

		$data = [
			'nome' => $user['cn'][0],
			'email' =>  $emailMasked,
			'cpf' => $user['description'][0],
		];

		return $this->successResponse($data);
	}

	public function sendToken(Request $request)
	{
		$data = [];
		$request->validate(
			[
				'cpf' => 'required|string|',
			],
			[
				'cpf.required' => 'O campo CPF é obrigatório para esta ação',
				'cpf.string' => 'O campo CPF precisa ser do tipo String'
			]
		);

		try {
			if (!$this->validateTimeSendToken($request->cpf)) {
				return $this->errorResponse("Um link de autorização já foi solicitado nas últimas 24 horas, aguarde para solicitar um novo.");
			}

			$cpfMasked = Helpers::formatCnpjCpf($request->cpf);

			$user = $this->connection->query()->whereIn('description', [$request->cpf, $cpfMasked])->first();

			if (!$user) {
				return $this->errorResponse("Nenhum usuário encontrado para o CPF informado.");
			}

			$email = $user['mail'][0];

			$data['cpf'] = $request->cpf;
			$data['nome'] = $user['cn'][0];
			$data['login'] = $user['samaccountname'][0];
			$data['created_at'] = now();
			$data['token'] = md5(uniqid(mt_rand(), true));
			$data['email'] = $email;

			$reset = AdPasswordReset::create($data);

			$linkChangePass = 'http://acessohomolog.faesa.br/#/auth-user/forgot-password-reset/' . $data['token'];

			$data['link'] = $linkChangePass;

			Mail::to('junior.devstack@gmail.com')->send(new ResetPassword($data));

			return $this->successResponse($reset);
		} catch (Exception $e) {
			Log::warning("Erro ao Enviar TOKEN: " . $e->getMessage());
		}
	}

	public function validateToken(Request $request)
	{
		$request->validate(
			[
				'token' => 'required|string|',
			],
			[
				'token.required' => 'O token é obrigatório para esta ação',
				'token.string' => 'o tipo do Token está incorreto',
			]
		);

		$tokenExists = AdPasswordReset::where('token', $request->token)->first();

		if (!$tokenExists) {
			return $this->errorResponse("token_invalido");
		}

		$tokenValidate = AdPasswordReset::whereNull('updated_at')
			->where('token', $request->token)
			->first();

		if (!$tokenValidate) {
			return $this->errorResponse("token_invalido");
		}

		$dateCreated = strtotime($tokenValidate->created_at);

		$currentTime = now()->timestamp;

		$diffTime = round(abs($dateCreated - $currentTime) / 60, 2);

		if (!$tokenValidate || $diffTime >= 720) {
			return $this->errorResponse("token_invalido");
		}

		$cpfMasked = Helpers::formatCnpjCpf($tokenValidate->cpf);

		$user = $this->connection->query()->whereIn('description', [$tokenValidate->cpf, $cpfMasked])->first();

		if (count($user) == 0) {
			return $this->errorResponse("Usuário não encontrado para o token informado!");
		}
		
		return $this->successResponse(
			[
				"user" =>  [
					'nome' => $user['cn'][0],
					'samaccountname' => $user['samaccountname'][0],
					'email' => $user['mail'][0],
				],
				"status_token" => "token_valido"
			]
		);
	}


	public function validateTimeSendToken($cpf)
	{
		$validTime = true;

		$tokenExists = AdPasswordReset::where('cpf', $cpf)
			->whereNull('updated_at')
			->max('created_at');

		if ($tokenExists) {
			$dateCreated = strtotime($tokenExists);
			$currentTime = now()->timestamp;
			$diffTime = round(abs($dateCreated - $currentTime) / 60, 2);

			if ($diffTime <= 720) {
				$validTime = false;
			}
		}

		return $validTime;
	}

	public function changePasswordPublic(Request $request)
	{
		$request->validate(
			[
				'password' => 'required',
				'token' => 'required|string|',
			],
			[
				'password.required' => 'O campo password é obrigatório para esta ação',
				'token.required' => 'O token é obrigatório para esta ação',
				'token.string' => 'o tipo do Token está incorreto',
			]
		);

		try {
			$validateToken = $this->validateToken($request)->getData();

			if (isset($validateToken->error)) {
				return $this->errorResponse('O Token não é válido');
			}

			$userToken = AdPasswordReset::where('token', $request->token)->first();

			$cpfMasked = Helpers::formatCnpjCpf($request->cpf);

			$userInfo = $this->connection->query()->whereIn('description', [$userToken->cpf, $cpfMasked])->get();
			$userCnFind = $userInfo[0]['dn'];

			$user = User::find($userCnFind);

			$user->unicodepwd  = $request->password;

			$user->save();
			$user->refresh();

			$this->changeStatusToken($request->token);

			$data = ['data' => 'Senha alterada com sucesso'];

			return $this->successMessage($data);
		} catch (InsufficientAccessException $ex) {
			Log::warning("ERRO ALTERAR SENHA: $ex");
		} catch (ConstraintViolationException $ex) {
			Log::warning("ERRO ALTERAR SENHA: $ex");
		} catch (\LdapRecord\LdapRecordException $ex) {
			$error = $ex->getDetailedError();

			echo $error->getErrorCode();
			echo $error->getErrorMessage();
			echo $error->getDiagnosticMessage();

			Log::warning("ERRO ALTERAR SENHA: " . $ex);
		}
	}

	public function changePasswordAdmin(Request $request)
	{
		$request->validate(
			[
				'password' => 'required',
				'cpf' => 'required|string|',
			],
			[
				'password.required' => 'O campo password é obrigatório para esta ação',
				'cpf.required' => 'O cpf é obrigatório para esta ação',
				'cpf.string' => 'o tipo do cpf está incorreto',
			]
		);

		try {
			$userInfo = $this->connection->query()->where('description', '=', $request->cpf)->get();
			$userCnFind = $userInfo[0]['dn'];

			$user = User::find($userCnFind);

			$user->unicodepwd  = $request->password;

			$user->save();
			$user->refresh();

			$data = ['data' => 'Senha alterada com sucesso'];

			return $this->successMessage($data);
		} catch (InsufficientAccessException $ex) {
			Log::warning("ERRO ALTERAR SENHA: $ex");
		} catch (ConstraintViolationException $ex) {
			Log::warning("ERRO ALTERAR SENHA: $ex");
		} catch (\LdapRecord\LdapRecordException $ex) {
			$error = $ex->getDetailedError();

			echo $error->getErrorCode();
			echo $error->getErrorMessage();
			echo $error->getDiagnosticMessage();

			Log::warning("ERRO ALTERAR SENHA: " . $ex);
		}
	}

	public function changeStatusToken($token)
	{
		try {
			$tokenAdPassword = AdPasswordReset::where('token', $token)->update(['updated_at' => now()]);
			return  $this->successResponse($tokenAdPassword);
		} catch (Exception $e) {
			Log::warning("ERRO AO ALTERAR STATUS TOKEN, AO ALTERAR A SENHA: " . $e);
		}
	}
}
