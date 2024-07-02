<?php

namespace App\Http\Controllers\Auth;

use Exception;
use App\Ldap\UserLdap;
use App\Utils\Helpers;
use App\Models\LyPessoa;
use App\Models\LyDocente;
use LdapRecord\Container;
use App\Models\LogUsersAd;
use App\Mail\ResetPassword;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Models\AdPasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use function Laravel\Prompts\warning;

use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Exceptions\ConstraintViolationException;

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
		$email='';

		if(!$request->has('tipoRegistro')){
			$request->validate(
				[
					'cpf' => 'required|string|',
					'g-recaptcha-response' => 'required',
				],
				[
					'cpf.required' => 'O campo CPF é obrigatório para esta ação',
					'cpf.string' => 'O campo CPF precisa ser do tipo String',
					'g-recaptcha-response.required' => 'g-recaptcha-response é obrigatório'
				]
			);

			$recaptchaValid = VerifyRecaptchaController::verifyToken($request);

			if($recaptchaValid['status'] == 'error'){
				return $this->errorResponse("Erro de validação reCAPTCHA.");
			}
		}

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

			foreach($user['memberof'] as $grupo){
				if($grupo === 'CN=Docentes,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br'){
					if(isset($user['pager'])){
						$email = $user['pager'][0];
					}else{
						$docente = DB::connection('sqlsrv_lyceum')->select("SELECT E_MAIL,CPF FROM LY_DOCENTE WHERE CPF in ('".$request->cpf."','".$cpfMasked."')");
						$email = $docente[0]->E_MAIL;
					}
					break;
				}

				if($grupo === 'CN=Alunos,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br'){
                    $aluno = DB::connection('sqlsrv_lyceum')
                                ->select("
                                    SELECT P.CPF, P.E_MAIL FROM LY_PESSOA P
                                    JOIN LY_ALUNO A ON P.PESSOA=A.PESSOA
                                    WHERE P.CPF IN ('".$request->cpf."','".$cpfMasked."')
                                            AND A.SIT_ALUNO = 'Ativo'
                                ");
                    if($aluno){
                        $email = $aluno[0]->E_MAIL;
                    }

					break;
				}
			}

			$data['cpf'] = $request->cpf;
			$data['nome'] = $user['cn'][0];
			$data['login'] = $user['samaccountname'][0];
			$data['created_at'] = now();
			$data['token'] = md5(uniqid(mt_rand(), true));
			$data['email'] = $email;

			$reset = AdPasswordReset::create($data);

			$linkChangePass = 'https://acesso.faesa.br/#/auth-user/forgot-password-reset/' . $data['token'];

			$data['link'] = $linkChangePass;

            try {
                Mail::to($email)->send(new ResetPassword($data));
            } catch (\Throwable $th) {
                LogUsersAd::create([
                    'nome' => $data['nome'],
                    'cpf' => $data['cpf'],
                    'matricula' => '',
                    'login' => $data['login'],
                    'evento' => 'AdPasswordReset',
                    'obs' => 'Falha no envio de email para reset de senha: ' . $th->getMessage(),
                    'status' => 'error'
                ]);
            }

            $emailMasked =  str::mask($email, '*', 4, 7);

			return $this->successResponse([
                'nome' => $data['nome'],
                'email' => $emailMasked,
                'cpf' => $data['cpf'],
            ]);
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

			$cpfMasked = Helpers::formatCnpjCpf($userToken->cpf);
			$userInfo = $this->connection->query()->whereIn('description', [$userToken->cpf, $cpfMasked])->get();
			$userCnFind = $userInfo[0]['dn'];

			$user = User::find($userCnFind);

			$user->unicodepwd  = $request->password;

			$user->save();
			$user->refresh();

			$this->changeStatusToken($request->token);

			$dataAtualizaLyceum = [
				'password' => $request->password,
				'cpf' => $userToken->cpf,
				'samaccountname' => $user->samaccountname[0],
			];

			$msgAlteraLyceum = $this->atualizarDadosLyceum($dataAtualizaLyceum);

			$data = [
				'warning' => $msgAlteraLyceum,
				'data' => 'Senha alterada com sucesso'
			];

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

			$dataAtualizaLyceum = [
				'password' => $request->password,
				'cpf' => $request->cpf,
				'samaccountname' => $user->samaccountname[0],
			];

			$msgAlteraLyceum = $this->atualizarDadosLyceum($dataAtualizaLyceum);

			$data = [
				'warning' => $msgAlteraLyceum,
				'data' => 'Senha alterada com sucesso'
			];

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

	private function atualizarDadosLyceum($data)
	{
		try {
			$cpfMasked = Helpers::formatCnpjCpf($data['cpf']);
			$cpf = trim(str_replace('-', '', str_replace('.', '', $data['cpf'])));
			$msgErro = '';

			$docente = LyDocente::whereIn('CPF', [$cpf, $cpfMasked])->first();
			$pessoa = LyPessoa::whereIn('CPF', [$cpf, $cpfMasked])->first();

			$senha = Helpers::cryptSenha($data['password']);

			if (!$pessoa) {
				$msgErro = "ERRO AO ATUALIZAR DADOS DO PROFESSOR NO LYCEUM, TABELA LY_PESSOA, DADOS NÃO ENCONTRADO PARA O CPF: " . $cpf;
				Log::warning($msgErro);
				return $msgErro;
			}else{
				$pessoa->WINUSUARIO = 'FAESA\\' . $data['samaccountname'];
				$pessoa->SENHA_TAC = Helpers::cryptSenha($data['password']);
				$pessoa->save();
			}

			if (!$docente) {
				$msgErro = "ERRO AO ATUALIZAR DADOS DO PROFESSOR NO LYCEUM, TABELA LY_DOCENTE, DADOS NÃO ENCONTRADO PARA O CPF: " . $cpf;
				Log::warning($msgErro);
				return $msgErro;
			}else{
				$docente->WINUSUARIO = 'FAESA\\' . $data['samaccountname'];
				$docente->SENHA_DOL = Helpers::cryptSenha($data['password']);
				$docente->save();

			}
			return $msgErro;

		} catch (Exception $e) {
			$msg = "ERRO AO ATUALIZAR DADOS NO LYCEUM: " . $e->getMessage();
			Log::warning($msg);
			return $this->errorResponse($msg);
		}
	}
}
