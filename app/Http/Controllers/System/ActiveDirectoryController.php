<?php

namespace App\Http\Controllers\System;

use Exception;
use App\Utils\Helpers;
use App\Models\LyPessoa;
use App\Models\LyDocente;
use LdapRecord\Container;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

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
	private $password;
	private $givenname;
	private $sn;
	private $complementNumericSmaAccount = 0;
	const CN_DEV = 'OU=Desenvolvimento,DC=faesa,DC=br';
	const CN_ALUNOS = 'OU=ATIVOS,OU=ALUNOS,OU=FAESA,DC=faesa,DC=br';
	const CN_FUNCIONARIOS = 'OU=Funcionarios_ADM,OU=Faesa,OU=Logins Iniciais,DC=faesa,DC=br';
	const CN_PROFESSORES = 'OU=Docente,OU=Faesa,OU=Logins Iniciais,DC=faesa,DC=br';

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
			// if (!$request->serialNumber) {
			// 	return $msgError = "Campo serialNumber é obrigatório o preenchimento";
			// } else

			// if (!preg_match(Helpers::patternFormat('patternSerialNumber'), $request->serialNumber)) {
			// 	return $msgError = "Formato serialNumber está incorreto";
			// }

			if ($request->serialNumber && !is_numeric($request->serialNumber)) {
				return $msgError = "SerialNumber deve ser numérico";
			}
		}

		if (!$request->cn) {
			return $msgError = "Campo cn é obrigatório o preenchimento";
		}

		// if ($request->userType != 'aluno' && !$request->manager) {
		// 	return $msgError = "Campo manager é obrigatório o preenchimento";
		// }

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

		// if (!$request->physicaldeliveryofficename) {
		// 	return $msgError = "Campo physicaldeliveryofficename é obrigatório o preenchimento";
		// } else
		// if (!$request->physicaldeliveryofficename) {
		// 	if (!preg_match(Helpers::patternFormat('patternPhysicalDeliveryOfficeName'), $request->physicaldeliveryofficename)) {
		// 		return $msgError = "Formato physicaldeliveryofficename está incorreto";
		// 	}
		// }

		if (!filter_var($request->mail, FILTER_VALIDATE_EMAIL)) {
			return $msgError = "E-mail inválido";
		}

		if ($request->userType == 'funcionario') {
			if (!$request->scriptpath) {
				return $msgError = "Campo scriptpath é obrigatório o preenchimento";
			}
		}

		if ($request->userType != 'funcionario')
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
			return $msgError = "Campo departament é obrigatório o preenchimento";
		}

		if (!$request->userType) {
			return $msgError = "Campo userType é obrigatório o preenchimento";
		}

		// if ($request->ipphone) {
		// 	if (!preg_match(Helpers::patternFormat('patternPhoneDigit'), $request->ipphone)) {
		// 		return $msgError = "Formato ipphone está incorreto";
		// 	}
		// }

		return $msgError;
	}

	public function createSamAccountNameGivenNameSn(Request $request)
	{
		$name = Helpers::clearName($request->cn);
		$namesDivided = explode(" ", $name);
		$firstName = strval($namesDivided[0]);
		$largerName = false;
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

			if (!$largerName) {
				if ($this->checkIfUserExists('account', $request)) {
					if (count($namesDivided) - 1 == $key) {
						while ($this->checkIfUserExists('account', $request)) {
							$this->complementNumericSmaAccount++;
							$this->samaccountname = $firstName . '.' . $secondName . strval($this->complementNumericSmaAccount);
						}
					}
					continue;
				}
			}
			// Checar o tamanho do login, se for maior do que 20, reduzir o segundo nome para a primeira letra e checar a existência do mesmo se já está registrado.
			if (strlen($this->samaccountname) > 20 || $largerName) {
				$largerName = true; // <== Necessário para continuar a validação da nova estrutura de login.
				$secondName = $namesDivided[1];

				$this->samaccountname = $firstName . '.' . substr($secondName, 0, 1) . strval($this->complementNumericSmaAccount == 0 ? '' : $this->complementNumericSmaAccount);

				while ($this->checkIfUserExists('account', $request)) {
					$this->complementNumericSmaAccount++;
					$this->samaccountname = $firstName . '.' . substr($secondName, 0, 1) . strval($this->complementNumericSmaAccount);
					// continue;
				}
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

		$user->givenname = ucfirst($this->givenname);
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
		$user->unicodePwd = "Faesa@" . substr($request->description, 0, 3);
		$user->proxyaddresses = "SMTP:" . $this->samaccountname . '@aluno.faesa.br';
		$user->userAccountControl = 512;

		try {
			if ($request->manager) {
				$user->manager = $request->manager;
			}

			if ($request->userType == 'aluno') {
				$user->userPrincipalName = $this->samaccountname . '@aluno.faesa.br';
			}

			$user->save();
			$user->refresh();

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

			$request['tipoRegistro'] = 'register_admin';

			$sendToken->sendToken($request);

			$this->password = $user->unicodePwd;

			$atualizaDadosLyceum = $this->atualizarDadosLyceum($request);

			$data = [
				'warning' => $atualizaDadosLyceum,
				'user' => $user
			];

			Log::info("USUÁRIO CRIADO EM $date, $user");

			return $this->successResponse($data);
		} catch (Exception  $ex) {
			Log::warning("ERRO AO CRIAR O USUÁRIO: CODE: $ex");
			echo $ex;
		}
	}

	private function atualizarDadosLyceum($request)
	{
		try {
			$cpfMasked = Helpers::formatCnpjCpf($request->description);
			$cpf = trim(str_replace('-', '', str_replace('.', '', $request->description)));
			$msgErro = '';
			$senhaCrypt = Helpers::cryptSenha($this->password);

			switch ($request->userType) {
				case 'aluno':
					$pessoa = LyPessoa::whereIn('CPF', [$cpf, $cpfMasked])->first();

					if ($pessoa) {
						$pessoa->WINUSUARIO = 'FAESA\/' . $this->samaccountname;
						$pessoa->SENHA_TAC = $senhaCrypt;
						$pessoa->save();
					} else {
						$msgErro = "ERRO AO ATUALIZAR DADOS DO ALUNO NO LYCEUM, DADOS NÃO ENCONTRADO PARA O CPF: " . $request->description;
						Log::warning($msgErro);
					}

					break;

				case 'professor':
					$docente = LyDocente::whereIn('CPF', [$cpf, $cpfMasked])->first();
					$pessoa = LyPessoa::whereIn('CPF', [$cpf, $cpfMasked])->first();

					if ($pessoa) {
						$pessoa->WINUSUARIO = 'FAESA\\' . $this->samaccountname;
						$pessoa->SENHA_TAC = $senhaCrypt;
						$pessoa->save();
					} else {
						$msgErro += "ERRO AO ATUALIZAR DADOS DO PROFESSOR NO LYCEUM, TABELA LY_PESSOA, DADOS NÃO ENCONTRADO PARA O CPF: " . $request->description;
						Log::warning($msgErro);
					}

					if ($docente) {
						$docente->WINUSUARIO = 'FAESA\\' . $this->samaccountname;
						$docente->SENHA_DOL = $senhaCrypt;
						$docente->save();
					}

					$msgErro += " ERRO AO ATUALIZAR DADOS DO PROFESSOR NO LYCEUM, TABELA LY_DOCENTE, DADOS NÃO ENCONTRADO PARA O CPF: " . $request->description;
					Log::warning($msgErro);

					break;
			}
			return $msgErro;
		} catch (Exception $e) {
			$msg = "ERRO AO ATUALIZAR DADOS NO LYCEUM: " . $e->getMessage();
			Log::warning($msg);
			return $this->errorResponse($msg);
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

		if (!$user) {
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

		if ($request->manager) {
			$user->manager = $request->manager;
		} else {
			$user->manager = null;
		}

		try {
			$user->save();
			// $user->refresh();

			$date = Date('d/m/y');

			if ($request->groups) {
				$groupController = new GroupController();

				$groupController->removeAllUserGroup($user);

				$groupController->addMemberGroupAll($request->groups, $user);
			}

			$this->samaccountname = $user->samaccountname;

			$atualizaDadosLyceum = $this->atualizarDadosLyceum($request);

			$data = [
				'warning' => $atualizaDadosLyceum,
				'user' => $user
			];

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
