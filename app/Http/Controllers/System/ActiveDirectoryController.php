<?php

namespace App\Http\Controllers\System;

use DateTime;
use Exception;
use DateTimeZone;
use Carbon\Carbon;
use App\Ldap\UserLdap;
use App\Utils\Helpers;
use LdapRecord\Container;
use App\Mail\ResetPassword;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LdapRecord\LdapRecordException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\ActiveDirectory\Group;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Exceptions\ConstraintViolationException;


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
                $check = $this->connection->query()->where('description', '=', $request->description)->get();
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
            $date = Date('dd/mm/yyyy');
            Log::info("USUÁRIO CRIADO EM $date, $user");
            return $this->successResponse($user);
        } catch (Exception  $ex) {
            Log::warning("ERRO AO CRIAR O USUÁRIO: CODE: $ex");
            echo $ex;
        }
    }

    public function changePassword($mail_user)
    {
        try {
            $content = [
                'body' => 'Test',
                'token' => random_bytes(4)
            ];

            $user = User::find('cn=Teste Teste da Silva Junior, OU=Desenvolvimento,DC=faesa,DC=br');

            $user->unicodepwd  = 'Faesa@202020';

            $user->save();
            $user->refresh();

            // Mail::to(['junior.devstack@gmail.com'])->send(new ResetPassword($content));

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

    public function listAllUsers(Request $request)
    {
        $listType = $request->listType;

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

        $users = $this->connection->query()->select('cn', 'mail', 'description', 'samaccountname', 'dateofBirth', 'serialNumber', 'accountexpires')->in($ouQuery)->where('useraccountcontrol', 512)->paginate(100);

        return response()->json(mb_convert_encoding($users, 'UTF-8'));
    }
}
