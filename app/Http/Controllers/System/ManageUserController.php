<?php

namespace App\Http\Controllers\System;

use LdapRecord\Container;
use App\Http\Controllers\Controller;
use App\Ldap\UserLdap;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;

class ManageUserController extends Controller
{
    use ApiResponser;

    private $connection;

    public function __construct(Container $connection) {
        $this->connection = $connection::getConnection('default');
    }

    public function listAllUsers()
    {
        // List to all users.
    }

    public function createUser(Request $request)
    {

        $userExist = UserLdap::findByOrFail('cn', 'Teste Teste2');

        if($userExist->exists){
            return $this->successResponse("Já existe um usuário criado com este nome");
        }

        $user = new UserLdap();
        $user->cn = 'Teste Teste2';
        $user->givenname = 'Steve';
        $user->sn = 'Bauman';
        $user->company = 'Acme';
        // $user->password = '12345657';
        $user->samaccountname = 'teste.teste2';

        $user->save();

        // $user = UserLdap::findByOrFail('samaccountname', 'jose.joselito');
        dd($user->getDn());

    }
}
