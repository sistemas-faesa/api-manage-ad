<?php

namespace App\Http\Controllers\System;

use App\Utils\Helpers;
use LdapRecord\Container;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\ActiveDirectory\Group;

class SearchController extends Controller
{
    use ApiResponser;

    private $connection;
    const CN_DEV = 'OU=Desenvolvimento,DC=faesa,DC=br';
    const CN_ALUNOS = 'OU=ATIVOS,OU=ALUNOS,OU=FAESA,DC=faesa,DC=br';
    const CN_FUNCIONARIOS = 'OU=ADMINISTRATIVO,OU=FUNCIONARIOS,OU=FAESA,DC=faesa,DC=br';
    const CN_PROFESSORES = 'OU=DOCENTE,OU=FUNCIONARIOS,OU=FAESA,DC=faesa,DC=br';

    public function __construct(Container $connection)
    {
        $this->connection = $connection::getConnection('default');
        ini_set('memory_limit', '-1');
    }

    public function searchUser(Request $request)
    {
        $finaList = [];
        $ouQuery = '';

        $request->validate(
            [
                'search' => 'required',
                'listType' => 'required',
            ],
            [
                'search.required' => 'O campo search é obrigatório para esta ação',
                'listType.required' => 'O campo listType é obrigatório para esta ação'
            ]
        );

        $listType = $request->listType;

        switch ($listType) {
            case 'aluno':
                $ouQuery = self::CN_ALUNOS;
                break;
            case 'funcionario':
                $ouQuery = self::CN_FUNCIONARIOS;
                break;
            case 'professor':
                $ouQuery = self::CN_PROFESSORES;
                break;
            case 'dev':
                $ouQuery = self::CN_DEV;
                break;
        }

        $searchValue = strip_tags($request->search);

        $cpfMasked = Helpers::formatCnpjCpf($searchValue);

        $userInfo = $this->connection->query()->in($ouQuery)->whereIn('description', [$request->search, $cpfMasked])->get();
        if (!$userInfo) {
            $userInfo = $this->connection->query()->in($ouQuery)->where('cn', 'contains', $searchValue)->get();
        }
        if (!$userInfo) {
            $userInfo = $this->connection->query()->in($ouQuery)->where('cn', 'contains', $searchValue)->get();
        }
        if (!$userInfo) {
            $userInfo = $this->connection->query()->in($ouQuery)->where('serialNumber', 'contains', $searchValue)->get();
        }
        if (!$userInfo) {
            $userInfo = $this->connection->query()->in($ouQuery)->where('mail', 'contains', $searchValue)->get();
        }
        if (!$userInfo) {
            $userInfo = $this->connection->query()->in($ouQuery)->where('physicaldeliveryofficename', 'contains', $searchValue)->get();
        }

        if ($userInfo) {
            foreach ($userInfo as $user) {
                array_push($finaList, [
                    'cn' => isset($user['cn']) ? $user['cn'][0] : 'NI',
                    'sn' => isset($user['sn']) ? $user['sn'][0] : 'NI',
                    'givenname' =>  isset($user['givenname']) ? $user['givenname'][0] : 'NI',
                    'displayname' => isset($user['displayname']) ? $user['displayname'][0] : 'NI',
                    'description' => isset($user['description']) ? $user['description'][0] : 'NI',
                    'dateofBirth' => isset($user['dateofBirth']) ? gettype(is_null($user['dateofbirth']) ? 0 : $user['dateofbirth']) == 'integer' ? 0 : $user['dateofbirth'][0] : 0,
                    'serialNumber' => isset($user['serialnumber']) ? $user['serialnumber'][0] : 'NI', //
                    'samaccountname' => isset($user['samaccountname']) ? $user['samaccountname'][0] : 'NI',
                    'pager' =>   isset($user['pager']) ? $user['pager'][0] : 'NI',
                    'physicaldeliveryofficename' => isset($user['physicaldeliveryofficename']) ? $user['physicaldeliveryofficename'][0] : 'NI',
                    'mail' => isset($user['mail']) ? $user['mail'][0] : 'NI',
                    'scriptpath' =>  isset($user['scriptpath']) ? $user['scriptpath'][0] : 'NI',
                    'ipphone' => isset($user['ipphone']) ? $user['ipphone'][0] : 'NI',
                    'title' => isset($user['title']) ? $user['title'][0] : 'NI',
                    'department' => isset($user['department']) ? $user['department'][0] : 'NI',
                    'company' => isset($user['company']) ? $user['company'][0] : 'NI',
                    'groups' => isset($user['groups']) ? $user['groups'] : 'NI',
                    'userAccountControl' => $user['useraccountcontrol'][0] == 512 ? true : false,
                    'accountexpires' => gettype($user['accountexpires']) == 'object' ? $user['accountexpires'][0] : 0,
                ]);
            }
        }

        return $this->successResponse($finaList);
    }

    public function listAllUsers(Request $request)
    {
        $listType = $request->listType;
        $finaList = [];

        $ouQuery = '';
        switch ($listType) {
            case 'aluno':
                $ouQuery = self::CN_ALUNOS;
                break;
            case 'funcionario':
                $ouQuery = self::CN_FUNCIONARIOS;
                break;
            case 'professor':
                $ouQuery = self::CN_PROFESSORES;
                break;
            case 'dev':
                $ouQuery = self::CN_DEV;
                break;
        }

        $users = $this->connection->query()
            ->select('cn', 'displayname', 'mail', 'description', 'samaccountname', 'dateofBirth', 'serialNumber', 'physicaldeliveryofficename', 'accountexpires')
            ->in($ouQuery)
            ->where('useraccountcontrol', 512)
            ->slice($page = $request->page, $perPage = $request->pageSize);

        foreach ($users as $user) {
            array_push($finaList, [
                'cn' => $user['cn'][0],
                'displayname' => isset($user['displayname']) ? $user['displayname'][0] : 'NI',
                'samaccountname' => $user['samaccountname'][0],
                'mail' => isset($user['mail']) ? $user['mail'][0] : 'NI',
                'cpf' => isset($user['description']) ? $user['description'][0] : 'NI',
                'dateofBirth' => isset($user['dateofBirth']) ? $user['dateofBirth'][0] : 'NI',
                'matriculaFunc' => isset($user['serialNumber']) ? $user['serialNumber'][0] : 'NI',
                'matricula' => isset($user['physicaldeliveryofficename']) ? $user['physicaldeliveryofficename'][0] : 'NI',
            ]);
        }

        $data = [
            'data' => $finaList,
            'total' => $users->total()
        ];

        return response()->json($data);
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

        $userInfo = $this->connection->query()->whereIn('description', [$request->cpf, $cpfMasked])->get();
        $userCnFind = $userInfo[0]['dn'];

        $user = User::find($userCnFind);

        if (!$user) {
            return $this->errorResponse("CPF Não encontrado!");
        }

        $data = [
            'cn' => $user->cn[0],
            'sn' => $user->sn[0],
            'givenname' =>  is_null($user->givenname) ? 'NI' : $user->givenname[0],
            'displayname' => is_null($user->displayname) ? 'NI' : $user->displayname[0],
            'description' => $user->description[0],
            'dateofBirth' => gettype(is_null($user->dateofBirth) ? 0 : $user->dateofBirth) == 'integer' ? 0 : $user->dateofBirth[0],
            'serialNumber' => is_null($user->serialNumber) ? 'NI' : $user->serialNumber[0],
            'pager' =>  is_null($user->pager) ? 'NI' : $user->pager[0],
            'physicaldeliveryofficename' => is_null($user->physicaldeliveryofficename) ? 'NI' : $user->physicaldeliveryofficename[0],
            'mail' => is_null($user->mail) ? 'NI' : $user->mail[0],
            'scriptpath' => is_null($user->scriptpath) ? 'NI' : $user->scriptpath[0],
            'ipphone' => is_null($user->ipphone) ? 'NI' : $user->ipphone[0],
            'title' => is_null($user->title) ? 'NI' : $user->title[0],
            'department' => is_null($user->department) ? 'NI' : $user->department[0],
            'company' => is_null($user->company) ? 'NI' : $user->company[0],
            'samaccountname' => isset($user['samaccountname']) ? $user['samaccountname'][0] : 'NI',
            'groups' => $user->groups,
            'userAccountControl' => $user->userAccountControl[0] == 512 ? true : false,
            'accountexpires' => gettype($user->accountexpires) == 'integer' ? 0 : $user->accountexpires,
        ];

        return $this->successResponse($data);
    }
}
