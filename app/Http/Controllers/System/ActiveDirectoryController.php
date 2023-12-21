<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Auth\SendTokenResetPasswordController;
use Exception;
use App\Utils\Helpers;
use LdapRecord\Container;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\ActiveDirectory\Group;
use Illuminate\Support\Str;

use function PHPUnit\Framework\isNull;

class ActiveDirectoryController extends Controller
{
    use ApiResponser;

    private $connection;
    private $samaccountname;
    private $givenname;
    private $sn;
    private $complementNumericSmaAccount = 0;
    const CN_DEV = 'OU=Desenvolvimento,DC=faesa,DC=br';
    const CN_ALUNOS = 'OU=ATIVOS,OU=ALUNOS,OU=FAESA,DC=faesa,DC=br';
    const CN_FUNCIONARIOS = 'OU=ADMINISTRATIVO,OU=FUNCIONARIOS,OU=FAESA,DC=faesa,DC=br';
    const CN_PROFESSORES = 'OU=DOCENTE,OU=FUNCIONARIOS,OU=FAESA,DC=faesa,DC=br';

    public function __construct(Container $connection)
    {
        $this->connection = $connection::getConnection('default');
        ini_set('memory_limit', '-1');
    }

    public function validateSaveUser(Request $request)
    {
        if (strlen($this->validarCampos($request)) > 0) {
            return $this->errorResponse($this->validarCampos($request), 400);
        }

        if ($this->checkIfUserExists('name', $request)) {
            return $this->errorResponse("Já existe um usuário criado com este nome");
        }

        if ($this->checkIfUserExists('email', $request)) {
            return $this->errorResponse("Este e-mail já encontra-se cadastrado.");
        }

        if ($this->checkIfUserExists('cpf', $request)) {
            return $this->errorResponse("Este cpf já encontra-se cadastrado.");
        }

        if ($this->checkIfUserExists('matricula', $request)) {
            return $this->errorResponse("Esta matrícula já encontra-se cadastrada.");
        }

        if ($this->checkIfUserExists('matricula_func', $request)) {
            return $this->errorResponse("Esta matrícula de funcionário já encontra-se cadastrada.");
        }

        $this->createSamAccountNameGivenNameSn($request);

        return $this->saveUser($request);
    }

    public function validarCampos(Request $request)
    {
        $msgError = "";

        if ($request->userType == 'funcionario') {
            if (!$request->dateofBirth) {
                return $msgError = "Campo dateofBirth é obrigatório o preenchimento para o usuário Funcionário";
            }

            if (!$request->serialNumber) {
                return $msgError = "Campo serialNumber é obrigatório o preenchimento";
            } elseif (!preg_match(Helpers::patternFormat('patternSerialNumber'), $request->serialNumber)) {
                return $msgError = "Formato serialNumber está incorreto";
            }
        }

        if (!$request->cn) {
            return $msgError = "Campo cn é obrigatório o preenchimento";
        }

        if (!strstr($request->cn, " ")) {
            return $msgError = "Nome com Formato incorreto!";
        }

        if (!$request->description) {
            return $msgError = "Campo description é obrigatório o preenchimento";
        } elseif (!preg_match(Helpers::patternFormat('patternCpf'), $request->description)) {
            return $msgError = "Formato description está incorreto";
        }

        if (!$request->physicaldeliveryofficename) {
            return $msgError = "Campo physicaldeliveryofficename é obrigatório o preenchimento";
        } elseif (!preg_match(Helpers::patternFormat('patternPhysicalDeliveryOfficeName'), $request->physicaldeliveryofficename)) {
            return $msgError = "Formato physicaldeliveryofficename está incorreto";
        }

        if (!filter_var($request->mail, FILTER_VALIDATE_EMAIL)) {
            return $msgError = "E-mail inválido";
        }

        if (!$request->scriptpath) {
            return $msgError = "Campo scriptpath é obrigatório o preenchimento";
        }

        if (!$request->pager) {
            return $msgError = "Campo pager é obrigatório o preenchimento";
        } elseif (!filter_var($request->pager, FILTER_VALIDATE_EMAIL)) {
            return $msgError = "pager inválido";
        }

        if (!$request->title) {
            return $msgError = "Campo title é obrigatório o preenchimento";
        }

        if (!$request->company) {
            return $msgError = "Campo company é obrigatório o preenchimento";
        }

        if (!$request->department) {
            return $msgError = "Campo department é obrigatório o preenchimento";
        }

        if (!$request->userType) {
            return $msgError = "Campo userType é obrigatório o preenchimento";
        }

        if (!$request->ipphone) {
            return $msgError = "Campo ipphone é obrigatório o preenchimento";
        } elseif (!preg_match(Helpers::patternFormat('patternPhoneDigit'), $request->ipphone)) {
            return $msgError = "Formato ipphone está incorreto";
        }

        return $msgError;
    }

    public function createSamAccountNameGivenNameSn(Request $request)
    {
        $name = Helpers::clearName($request->cn);
        $namesDivided = explode(" ", $name);
        $firstName = strval($namesDivided[0]);
        $this->givenname = $firstName;

        foreach ($namesDivided as $key => $name) {
            if (strlen($name) < 3 || $key == 0) {
                continue;
            }

            $secondName = strval($namesDivided[$key]);
            // Aplicar o segundo nome ao SN.
            if ($key == 2) {
                $this->sn = ucfirst($secondName);
            }
            $this->samaccountname = $firstName . '.' . $secondName . strval($this->complementNumericSmaAccount == 0 ? '' : $this->complementNumericSmaAccount);

            if ($this->checkIfUserExists('account', $request)) {
                if (count($namesDivided) - 1 == $key) {
                    $this->complementNumericSmaAccount = random_int(1, 99);
                    $this->samaccountname = $firstName . '.' . $secondName . strval($this->complementNumericSmaAccount);
                }
                continue;
            }
            break;
        }
    }

    private function checkIfUserExists(string $type, Request $request = null)
    {
        $check = [];
        $cpfMasked = Helpers::formatCnpjCpf($request->description);

        switch ($type) {
            case 'account':
                $check = $this->connection->query()->where('samaccountname', '=', $this->samaccountname)->get();
                break;
            case 'name':
                $check = $this->connection->query()->where('cn', '=', $request->cn)->get();
                break;
            case 'email':
                $check = $this->connection->query()->where('mail', '=', $request->mail)->get();
                break;
            case 'cpf':
                $check = $this->connection->query()->whereIn('description', [$request->description, $cpfMasked])->get();
                break;
            case 'matricula':
                $check = $this->connection->query()->where('physicaldeliveryofficename', '=', $request->physicaldeliveryofficename)->get();
                break;
            case 'matricula_func':
                $check = $this->connection->query()->where('serialnumber', '=', $request->serialnumber)->get();
                break;
        }
        return $check;
    }

    private function saveUser(Request $request)
    {
        switch ($request->userType) {
            case 'aluno':
                $user = (new User)->inside(self::CN_ALUNOS);
                break;
            case 'funcionario':
                $user = (new User)->inside(self::CN_FUNCIONARIOS);
                $user->dateofbirth = $request->dateofBirth;
                $user->serialnumber = $request->serialNumber;
                break;
            case 'professor':
                $user = (new User)->inside(self::CN_PROFESSORES);
                if ($request->accountexpires) {
                    $accountexpires = strtotime($request->accountexpires);
                    $user->accountexpires = $accountexpires;
                }
                break;
            case 'dev':
                $user = (new User)->inside(self::CN_DEV);
                break;
        }
        $request['cpf'] = $request->description;

        $user->givenname = $this->givenname;
        $user->displayname = $request->cn;
        $user->cn = $request->cn;
        $user->sn = $this->sn;
        $user->description = $request->description;
        $user->physicaldeliveryofficename = $request->physicaldeliveryofficename;
        $user->mail = $request->mail;
        $user->samaccountname = $this->samaccountname;
        $user->scriptpath = $request->scriptpath;
        $user->ipphone = $request->ipphone;
        $user->title = $request->title;
        $user->department = $request->department;
        $user->company = $request->company;
        $user->unicodePwd = "Faesa@2023";
        $user->proxyaddresses = "SMTP:" . $request->mail;
        $user->userAccountControl = 512;
        try {

            $user->save();
            $user->refresh();
            $user->manager()->attach($user);
            $date = Date('d/m/y');

            $groupController = new GroupController();

            switch ($request->userType) {
                case 'aluno':
                    $groupController->addMemberGroupAlunos($user);
                    break;
                case 'funcionario':
                    $groupController->addMemberGroupFuncionarios($user);
                    break;
                case 'professor':
                    $groupController->addMemberGroupProfessores($user);
                    break;
                case 'dev':
                    $groupController->addMemberGroupDev($user);
                    break;
            }

            if ($request->groups) {
                $groupController = new GroupController();
                $groupController->addMemberGroupAll($request->groups, $user);
            }

            $connection = new Container();
            $sendToken = new SendTokenResetPasswordController($connection);

            $sendToken->sendToken($request);

            $data = [
                'user' => $user,
                'token' => $sendToken,
            ];

            Log::info("USUÁRIO CRIADO EM $date, $user");

            return $this->successResponse($data);
        } catch (Exception  $ex) {
            Log::warning("ERRO AO CRIAR O USUÁRIO: CODE: $ex");
            echo $ex;
        }
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
            'groups' => $user->groups,
            'userAccountControl' => $user->userAccountControl[0],
            'accountexpires' => gettype($user->accountexpires) == 'integer' ? 0 : $user->accountexpires,
        ];

        return $this->successResponse($data);
    }

    public function changeUser(Request $request)
    {
        $request->validate(
            [
                'description' => 'required|string',
            ],
            [
                'description.required' => 'O campo description é obrigatório para esta ação',
                'description.string' => 'o tipo do description está incorreto',
            ]
        );

        $cpfMasked = Helpers::formatCnpjCpf($request->description);

        $userInfo = $this->connection->query()->whereIn('description', [$request->description, $cpfMasked])->get();
        $userCnFind = $userInfo[0]['dn'];

        $user = User::find($userCnFind);

        if ($user) {
            $this->errorResponse('Nenhum usuário encontrado para o CPF informado!');
        }

        $user->givenname = $request->givenname;
        $user->displayname = $request->displayname;
        $user->serialNumber = $request->serialNumber;
        $user->pager = $request->pager;
        $user->dateofBirth  = $request->dateofBirth;
        $user->description = $request->description;
        $user->physicaldeliveryofficename = $request->physicaldeliveryofficename;
        $user->mail = $request->mail;
        $user->scriptpath = $request->scriptpath;
        $user->ipphone = $request->ipphone;
        $user->title = $request->title;
        $user->department = $request->department;
        $user->company = $request->company;
        $user->userAccountControl = 512;
        $accountexpires = strtotime($request->accountexpires);
        $user->accountexpires = $accountexpires;
        try {
            $user->save();
            $user->refresh();

            $date = Date('d/m/y');

            if ($request->groups) {
                $groupController = new GroupController();
                $groupController->addMemberGroupAll($request->groups, $user);
            }

            $data = [
                'user' => $user,
            ];

            Log::info("USUÁRIO ALTERADO EM $date, $user");

            return $this->successResponse($data);
        } catch (Exception  $ex) {
            Log::warning("ERRO AO ALTERAR O USUÁRIO: CODE: $ex");
            echo $ex;
        }
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
                    'pager' =>   isset($user['pager']) ? $user['pager'][0] : 'NI',
                    'physicaldeliveryofficename' => isset($user['physicaldeliveryofficename']) ? $user['physicaldeliveryofficename'][0] : 'NI',
                    'mail' => isset($user['mail']) ? $user['mail'][0] : 'NI',
                    'scriptpath' =>  isset($user['scriptpath']) ? $user['scriptpath'][0] : 'NI',
                    'ipphone' => isset($user['ipphone']) ? $user['ipphone'][0] : 'NI',
                    'title' => isset($user['title']) ? $user['title'][0] : 'NI',
                    'department' => isset($user['department']) ? $user['department'][0] : 'NI',
                    'company' => isset($user['company']) ? $user['company'][0] : 'NI',
                    'groups' => isset($user['groups']) ? $user['groups'] : 'NI',
                    'userAccountControl' => $user['useraccountcontrol'][0],
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
