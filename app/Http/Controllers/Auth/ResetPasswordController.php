<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use LdapRecord\Container;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
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

        $user = $this->connection->query()->where('description', '=', $request->cpf)->first();

        if(!$user){
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

    public function changePassword(Request $request)
    {
        $request->validate(
            [
                'password' => 'required|string|',
                'confirmPassword' => 'required|string|',
            ],
            [
                'cpf.required' => 'O campo CPF é obrigatório para esta ação',
                'cpf.string' => 'O campo CPF precisa ser do tipo String'
            ]
        );
    }
}
