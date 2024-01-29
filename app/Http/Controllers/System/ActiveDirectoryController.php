<?php

namespace App\Http\Controllers\System;

use Exception;
use App\Utils\Helpers;
use LdapRecord\Container;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use function PHPUnit\Framework\isNull;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\Timestamp;

use LdapRecord\Models\ActiveDirectory\Group;
use App\Http\Controllers\Auth\SendTokenResetPasswordController;

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

	public function __construct()
	{
		$this->connection = Container::getDefaultConnection();
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

		if (strpos($request->cn, ' ') === false) {
			return $msgError = "Nome com Formato incorreto!";
		}

		if (!preg_match('/^[\p{L}\s]+$/u', $request->cn)) {
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
			if ($key == 1) {
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
		$cpf = trim(str_replace('-', '', str_replace('.', '', $request->description)));

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
				$check = $this->connection->query()->whereIn('description', [$cpf, $cpfMasked])->get();
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
		$user->pager = $request->pager;
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

	public function changeUser(Request $request)
	{
		$request->validate(
			[
				'description' => 'required|string',
				'userAccountControl' => 'boolean',
			],
			[
				'description.required' => 'O campo description é obrigatório para esta ação',
				'description.string' => 'o tipo do description está incorreto',
				'userAccountControl.boolean' => 'o tipo do userAccountControl está incorreto, deve ser um boolean',
			]
		);

		$cpfMasked = Helpers::formatCnpjCpf($request->description);

		$userInfo = $this->connection->query()->whereIn('description', [$request->description, $cpfMasked])->get();

		$userCnFind = $userInfo[0]['dn'];

		$user = User::find($userCnFind);

		if ($user) {
			$this->errorResponse('Nenhum usuário encontrado para o CPF informado!');
		}

		if ($request->accountexpires == 0) {
			$accountexpires = Timestamp::WINDOWS_INT_MAX;
		} else {
			$accountexpires = $request->accountexpires;
		}

		$user->givenname = $request->givenname;
		// $user->displayname = $request->displayname;
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
		$user->userAccountControl = $request->userAccountControl ? 512 : 512 + 2;
		$user->accountexpires = $accountexpires;

		try {
			$user->save();
			// $user->refresh();

			$date = Date('d/m/y');

			if ($request->groups) {
				$groupController = new GroupController();

				$groupController->removeAllUserGroup($user);

				$groupController->addMemberGroupAll($request->groups, $user);
			}

			$data = [
				'info' => $user,
			];

			Log::info("USUÁRIO ALTERADO EM $date, $user");

			return $this->successResponse($data);
		} catch (Exception  $ex) {
			Log::warning("ERRO AO ALTERAR O USUÁRIO: CODE: $ex");
			return $this->errorResponse($ex->getMessage());
		}
	}
}
