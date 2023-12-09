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
use Illuminate\Support\Facades\Mail;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Exceptions\ConstraintViolationException;
use LdapRecord\Models\ActiveDirectory\User as ActiveDirectoryUser;

class SendTokenResetPasswordController extends Controller
{
    use ApiResponser;

    private $connection;

    public function __construct(Container $connection)
    {
        $this->connection = $connection::getConnection('default');
        ini_set('memory_limit', '-1');
    }

    public function sendToken(Request $request)
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

        try {

            if (!$this->validateTimeSendToken($request->cpf)) {
                return $this->errorResponse("Um link de autorização já foi solicitado nos últimos 30 minutos, aguarde para solicitar um novo.");
            }

            $user = $this->connection->query()->where('description', '=', $request->cpf)->first();

            if (!$user) {
                return $this->errorResponse("Nenhum usuário encontrado para o CPF informado.");
            }

            $email = $user['mail'][0];

            $data['cpf'] = $request->cpf;
            $data['created_at'] = now();
            $data['token'] = md5(uniqid(mt_rand(), true));
            $data['email'] = $email;

            $reset = AdPasswordReset::create($data);

            $linkChangePass = 'http://acessohomolog.faesa.br/#/auth-user/forgot-password-reset/' . $data['token'];

            Mail::to('junior.devstack@gmail.com')->send(new ResetPassword($linkChangePass));

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

        return $this->successResponse("token_valido");
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

    public function changePassword(Request $request)
    {
        try {

            if($this->validateToken($request->token) == 'token_invalido'){
                return $this->errorResponse('O Token não é mais válido');
            }

            $user = $this->connection->query()->where('description', '=', $request->cpf)->get();

            $user->unicodepwd  = $request->password;

            $user->save();
            $user->refresh();

            $this->changeStatusToken($request->token);

            return $this->successMessage('e-mail enviado com sucesso!');
        } catch (InsufficientAccessException $ex) {
            Log::warning("ERRO ALTERAR SENHA: $ex");
        } catch (ConstraintViolationException $ex) {
            Log::warning("ERRO ALTERAR SENHA: $ex");
        } catch (\LdapRecord\LdapRecordException $ex) {
            $error = $ex->getDetailedError();

            echo $error->getErrorCode();
            echo $error->getErrorMessage();
            echo $error->getDiagnosticMessage();

            Log::warning("ERRO ALTERAR SENHA: $error");
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
