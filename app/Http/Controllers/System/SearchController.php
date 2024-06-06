<?php

namespace App\Http\Controllers\System;

use Exception;
use App\Utils\Helpers;
use LdapRecord\Container;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LdapRecord\LdapRecordException;
use App\Http\Controllers\Controller;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\ActiveDirectory\Group;
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Exceptions\ConstraintViolationException;

class SearchController extends Controller
{
    use ApiResponser;

    private $connection;
    const CN_DEV = 'OU=Desenvolvimento,DC=faesa,DC=br';

    // const CN_ALUNOS_OU = 'OU=ATIVOS,OU=ALUNOS,OU=FAESA,DC=faesa,DC=br';
    // const CN_FUNCIONARIOS_OU = 'OU=Funcionarios_ADM,OU=Faesa,OU=Logins Iniciais,DC=faesa,DC=br';
    // const CN_PROFESSORES_OU = 'OU=Docente,OU=Faesa,OU=Logins Iniciais,DC=faesa,DC=br';

    const CN_ALUNOS = 'CN=Alunos,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br';
    const CN_FUNCIONARIOS = 'CN=Funcionarios,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br';
    const CN_PROFESSORES = 'CN=Docentes,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br';

    public function __construct()
    {
        $this->connection = Container::getDefaultConnection();
    }

    public function searchUser(Request $request)
    {
        try {
            $finaList = [];

            $this->validateSearchRequest($request);

            $listType = $request->listType;

            $ouQuery = $this->getGroupQuery($listType);

            $searchValue = strip_tags($request->search);

            $cpfMasked = Helpers::formatCnpjCpf($request->cpf);
            $cpf = trim(str_replace('-', '', str_replace('.', '', $request->cpf)));

            $userInfo = $this->connection->query()->in($ouQuery)->whereIn('description', [$cpf, $cpfMasked])->get();
            if (empty($userInfo)) {
                $userInfo = $this->connection->query()->in($ouQuery)->where('cn', 'contains', $searchValue)->get();
            }
            if (empty($userInfo)) {
                $userInfo = $this->connection->query()->in($ouQuery)->where('sn', 'contains', $searchValue)->get();
            }
            if (empty($userInfo)) {
                $userInfo = $this->connection->query()->in($ouQuery)->where('serialNumber', 'contains', $searchValue)->get();
            }
            if (empty($userInfo)) {
                $userInfo = $this->connection->query()->in($ouQuery)->where('mail', 'contains', $searchValue)->get();
            }
            if (empty($userInfo)) {
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
        } catch (Exception $e) {
            $this->logError("ERRO AO BUSCAR USUÁRIO CHAVE ÚNICA DE PESQUISA: $e");
        } catch (LdapRecordException $ex) {
            $this->logError("ERRO AO BUSCAR USUÁRIO CHAVE ÚNICA DE PESQUISA: " . $ex->getDetailedError());
        }
    }

    public function listAllUsers(Request $request)
    {
        try {
            $this->validateListRequest($request);

            $listType = $request->listType;

            $query = $this->getGroupQuery($listType);

            if($request->isManager){
                $query = $this->getGroupQuery('funcionario');
            }

            $group = Group::find($query);

            $members = $group->members();

            if ($request->cpf) {
                $cpfMasked = Helpers::formatCnpjCpf($request->cpf);
                $cpf = trim(str_replace('-', '', str_replace('.', '', $request->cpf)));

                $members = $members->where('description', [$cpf, $cpfMasked]);
            } else

            if ($request->search) {
                $searchValue = strip_tags($request->search);

                $members = $members->where('cn', 'contains', $searchValue);
                $members = $members->orWhere('serialNumber', 'contains', $searchValue);
                $members = $members->orWhere('mail', 'contains', $searchValue);
                $members = $members->orWhere('physicaldeliveryofficename', 'contains', $searchValue);
                $members = $members->orWhere('displayname', 'contains', $searchValue);
                $members = $members->orWhere('givenname', 'contains', $searchValue);
                $members = $members->orWhere('description', 'contains', $searchValue);
                $members = $members->orWhere('samaccountname', 'contains', $searchValue);
            }

            $results = [];
            $page = $request->page;
            $pageSize = $request->pageSize;
            //$pageSize = 1;

            $members->chunk($page * $pageSize, function ($members) use (&$results) {
                $results = array_map(function ($member) {
                    return [
                        'cn' => $member['cn'][0] ?? 'NI',
                        'sn' => $member['sn'][0] ?? 'NI',
                        'givenname' =>  $member['givenname'][0] ?? 'NI',
                        'displayname' => $member['displayname'][0] ?? 'NI',
                        'cpf' => $member['description'][0] ?? 'NI',
                        'description' => $member['description'][0] ?? 'NI',
                        'dateofBirth' => isset($member['dateofBirth']) ? (gettype(is_null($member['dateofbirth']) ? 0 : $member['dateofbirth']) == 'integer' ? 0 : $member['dateofbirth'][0]) : 0,
                        'serialNumber' => $member['serialnumber'][0] ?? 'NI', //
                        'samaccountname' => $member['samaccountname'][0] ?? 'NI',
                        'pager' =>   $member['pager'][0] ?? 'NI',
                        'physicaldeliveryofficename' => $member['physicaldeliveryofficename'][0] ?? 'NI',
                        'mail' => $member['mail'][0] ?? 'NI',
                        'scriptpath' =>  $member['scriptpath'][0] ?? 'NI',
                        'ipphone' => $member['ipphone'][0] ?? 'NI',
                        'title' => $member['title'][0] ?? 'NI',
                        'department' => $member['department'][0] ?? 'NI',
                        'company' => $member['company'][0] ?? 'NI',
                        'groups' => $member['memberof'] ?? 'NI',
                        'userAccountControl' => isset($member['useraccountcontrol']) ? ($member['useraccountcontrol'][0] == 512 ? true : false) : false,
                        'accountexpires' => isset($member['accountexpires']) ? $member['accountexpires'][0] : 0,
                        'distinguishedname' => isset($member['distinguishedname']) ? $member['distinguishedname'][0] : "",
                        'objectguid' => isset($member['objectguid']) ? $member['objectguid'][0] : "",
                        'objectsid' => isset($member['objectsid']) ? $member['objectsid'][0] : "",
                    ];
                }, $members->toArray());

                //$results = $members;

                return false;
            });

            $data = [
                'data' => $results,
                'total' => 100000000
            ];

            return response()->json($data);
        } catch (Exception $e) {
            $this->logError("ERRO AO LISTAR USUÁRIOS: $e");
        } catch (LdapRecordException $ex) {
            $this->logError("ERRO AO BUSCAR USUÁRIO POR CPF: " . $ex->getDetailedError());
        }
    }

    public function getUserByCpf(Request $request)
    {
        try {
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
            $cpf = trim(str_replace('-', '', str_replace('.', '', $request->cpf)));

            $userInfo = $this->connection->query()->whereIn('description', [$cpf, $cpfMasked])->get();

            if (count($userInfo) == 0) {
                return $this->errorResponse("CPF Não encontrado!");
            }

            $userCnFind = $userInfo[0]['dn'];

            $user = User::find($userCnFind);

            if (!$user) {
                return $this->errorResponse("CPF Não encontrado!");
            }

            $data = [
                'cn' => $user->cn[0] ?? 'NI',
                'sn' => $user->sn[0] ?? 'NI',
                'givenname' =>  $user->givenname[0] ?? 'NI',
                'displayname' => $user->displayname[0] ?? 'NI',
                'description' => $user->description[0],
                'dateofBirth' => gettype($user->dateofBirth[0] ?? 0) == 'integer' ? 0 : $user->dateofBirth[0],
                'serialNumber' => $user->serialNumber[0] ?? 'NI',
                'pager' =>  $user->pager[0] ?? 'NI',
                'physicaldeliveryofficename' => $user->physicaldeliveryofficename[0] ?? 'NI',
                'mail' => $user->mail[0] ?? 'NI',
                'scriptpath' => $user->scriptpath[0] ?? 'NI',
                'ipphone' => $user->ipphone[0] ?? 'NI',
                'title' => $user->title[0] ?? 'NI',
                'department' => $user->department[0] ?? 'NI',
                'company' => $user->company[0] ?? 'NI',
                'samaccountname' => isset($user->samaccountname) ? $user->samaccountname[0] : 'NI',
                'userAccountControl' => $user->userAccountControl[0] == 512 ? true : false,
                'accountexpires' => gettype($user->accountexpires) == 'integer' ? 0 : $user->accountexpires,
                'groups' => isset($user['memberof']) ? $user->memberof : 'NI',
                'manager' => isset($user['manager']) ? $user->manager[0] : 'NI',
                'managerObj' => isset($user['manager']) ? ['id' => $user->manager[0]] : [],
            ];

            return $this->successResponse($data);
        } catch (Exception $e) {
            $this->logError("ERRO AO BUSCAR USUÁRIO POR CPF: $e");
        } catch (LdapRecordException $ex) {
            $this->logError("ERRO AO BUSCAR USUÁRIO POR CPF: " . $ex->getDetailedError());
        }
    }

    private function getGroupQuery($listType)
    {
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

        return $ouQuery;
    }

    // private function getOuQuery($listType)
    // {
    //     switch ($listType) {
    //         case 'aluno':
    //             $ouQuery = self::CN_ALUNOS_OU;
    //             break;
    //         case 'funcionario':
    //             $ouQuery = self::CN_FUNCIONARIOS_OU;
    //             break;
    //         case 'professor':
    //             $ouQuery = self::CN_PROFESSORES_OU;
    //             break;
    //         case 'dev':
    //             $ouQuery = self::CN_DEV;
    //             break;
    //     }

    //     return $ouQuery;
    // }

    private function validateSearchRequest(Request $request)
    {
        $request->validate([
            'search' => 'required',
            'listType' => 'required',
        ], [
            'search.required' => 'O campo search é obrigatório para esta ação',
            'listType.required' => 'O campo listType é obrigatório para esta ação'
        ]);
    }

    private function validateListRequest(Request $request)
    {
        $request->validate(
            [
                'listType' => 'required',
            ],
            [
                'listType.required' => 'O campo listType é obrigatório para esta ação'
            ]
        );
    }

    private function logError(string $message)
    {
        Log::warning("ERRO LDAP: $message");
    }
}
