<?php

namespace App\Http\Controllers\System;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Support\Facades\Log;
use LdapRecord\Models\ActiveDirectory\Group;

class GroupController extends Controller
{
    use ApiResponser;

    const ALUNOS = [
        'CN=AlunoMS,OU=Grupos,OU=Grupos Office365,DC=faesa,DC=br',
        'CN=Alunos,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br',
        'CN=Wireless Alunos,OU=Wireless,DC=faesa,DC=br'
    ];

    const FUNCIONARIOS = [
        'CN=Funcionarios,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br',
        'CN=Todos Funcionarios Administrativos,OU=Grupos,OU=Grupos Office365,DC=faesa,DC=br',
        'CN=TodosFAESA,OU=Grupos,OU=Grupos Office365,DC=faesa,DC=br',
        'CN=Wireless Administrativo,OU=Wireless,DC=faesa,DC=br'
    ];

    const PROFESSORES = [
        'CN=Docentes,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br',
        'CN=Funcionarios,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br',
        'CN=Todos os Docentes,OU=Grupos,OU=Grupos Office365,DC=faesa,DC=br',
        'CN=TodosFAESA,OU=Grupos,OU=Grupos Office365,DC=faesa,DC=br',
        'CN=Wireless Professores,OU=Wireless,DC=faesa,DC=br'
    ];

    const DEVS = [
        'CN=GG_TESTE01,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br',
        'CN=GG_TESTE02,OU=FAESA,DC=faesa,DC=br',
        'CN=GG_TESTE03,OU=Nucleo de Tecnologia da Informacao,OU=Faesa,OU=Logins Iniciais,DC=faesa,DC=br'
    ];

    public function addMemberGroupAlunos($user)
    {
        try {
            foreach (self::ALUNOS as $aluno) {
                $group = Group::find($aluno);
                $group->members()->attach($user);
            }
        } catch (Exception $ex) {
            Log::warning("ERRO AO ADICIONAR GRUPO DEV: " . $ex);
        }
    }

    public function addMemberGroupFuncionarios($user)
    {
        try {
            foreach (self::FUNCIONARIOS as $funcionario) {
                $group = Group::find($funcionario);
                $group->members()->attach($user);
            }
        } catch (Exception $ex) {
            Log::warning("ERRO AO ADICIONAR GRUPOS FUNCIONÁRIOS: " . $ex);
        }
    }

    public function addMemberGroupProfessores($user)
    {
        try {
            foreach (self::PROFESSORES as $professor) {
                $group = Group::find($professor);
                $group->members()->attach($user);
            }
        } catch (Exception $ex) {
            Log::warning("ERRO AO ADICIONAR GRUPOS PROFESSORES: " . $ex);
        }
    }

    public function addMemberGroupDev($user)
    {
        try {
            foreach (self::DEVS as $dev) {
                $group = Group::find($dev);
                $group->members()->attach($user);
            }
        } catch (Exception $ex) {
            Log::warning("ERRO AO ADICIONAR GRUPOS DEV: " . $ex);
        }
    }

    public function addMemberGroupAll($groups, $user)
    {
        try {
            foreach ($groups as $group) {
                $group = Group::find($group);
                $group->members()->attach($user);
            }
        } catch (Exception $ex) {
            Log::warning("ERRO AO ADICIONAR USUÁRIO GRUPO PONTUAL: " . $ex);
        }
    }

    public function getGroups()
    {
        $finaList = [];
        $groups = Group::all();

        foreach ($groups as $group) {
            array_push($finaList, [
                'cn' => $group->cn,
                'description' => $group->description,
                'distinguishedname' => $group->distinguishedname
            ]);
        }

        return $this->successResponse($finaList);
    }

    public function getMembersGroup(Request $request)
    {
        if (!$request->dn) {
            return $this->errorResponse("campo dn é obrigatório");
        }

        $group = Group::find($request->dn);

        // $group->members()->detach($user);

        return $this->successResponse($group);
    }
}
