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

            Mail::to($email)->send(new ResetPassword($data['token']));

            return $this->successResponse($reset);
        } catch (Exception $e) {
            Log::warning("Erro ao Enviar TOKEN: " . $e->getMessage());
        }
    }
}
