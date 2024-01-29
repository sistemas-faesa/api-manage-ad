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
	const CN_ALUNOS = 'OU=ATIVOS,OU=ALUNOS,OU=FAESA,DC=faesa,DC=br';
	const CN_FUNCIONARIOS = 'OU=ADMINISTRATIVO,OU=FUNCIONARIOS,OU=FAESA,DC=faesa,DC=br';
	const CN_PROFESSORES = 'OU=DOCENTE,OU=FUNCIONARIOS,OU=FAESA,DC=faesa,DC=br';

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

			$ouQuery = $this->getOuQuery($listType);

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
			$finaList = [];

			$ouQuery = $this->getOuQuery($listType);

			if (!$request->has('search')) {
				$request['search'] = '?';
			}

			$searchValue = strip_tags($request->search);

			$cpfMasked = Helpers::formatCnpjCpf($request->cpf);
			$cpf = trim(str_replace('-', '', str_replace('.', '', $request->cpf)));

			$users = $this->connection->query()->in($ouQuery)->whereIn('description', [$cpf, $cpfMasked])->slice($page = $request->page, $perPage = $request->pageSize);
			
			if ($users->total() == 0) {
				$users = $this->connection->query()->in($ouQuery)->where('cn', 'contains', $searchValue)->slice($page = $request->page, $perPage = $request->pageSize);
			}
			if ($users->total() == 0) {
				$users = $this->connection->query()->in($ouQuery)->where('cn', 'contains', $searchValue)->slice($page = $request->page, $perPage = $request->pageSize);
			}
			if ($users->total() == 0) {
				$users = $this->connection->query()->in($ouQuery)->where('serialNumber', 'contains', $searchValue)->slice($page = $request->page, $perPage = $request->pageSize);
			}
			if ($users->total() == 0) {
				$users = $this->connection->query()->in($ouQuery)->where('mail', 'contains', $searchValue)->slice($page = $request->page, $perPage = $request->pageSize);
			}
			if ($users->total() == 0) {
				$users = $this->connection->query()->in($ouQuery)->where('physicaldeliveryofficename', 'contains', $searchValue)->slice($page = $request->page, $perPage = $request->pageSize);
			}
			
			if($request['search'] == '?'){
				if ($users->total() == 0) {
					$users = $this->connection->query()
					->select('cn', 'displayname', 'mail', 'description', 'samaccountname', 'dateofBirth', 'serialNumber', 'physicaldeliveryofficename', 'accountexpires')
					->in($ouQuery)
					->slice($page = $request->page, $perPage = $request->pageSize);
					
				}
			}
			
			foreach ($users as $user) {
				array_push($finaList, [
					'cn' => $user['cn'][0] ?? 'NI',
					'sn' => $user['sn'][0] ?? 'NI',
					'givenname' =>  $user['givenname'][0] ?? 'NI',
					'displayname' => $user['displayname'][0] ?? 'NI',
					'cpf' => $user['description'][0] ?? 'NI',
					'dateofBirth' => isset($user['dateofBirth']) ? (gettype(is_null($user['dateofbirth']) ? 0 : $user['dateofbirth']) == 'integer' ? 0 : $user['dateofbirth'][0]) : 0,
					'serialNumber' => $user['serialnumber'][0] ?? 'NI', //
					'samaccountname' => $user['samaccountname'][0] ?? 'NI',
					'pager' =>   $user['pager'][0] ?? 'NI',
					'physicaldeliveryofficename' => $user['physicaldeliveryofficename'][0] ?? 'NI',
					'mail' => $user['mail'][0] ?? 'NI',
					'scriptpath' =>  $user['scriptpath'][0] ?? 'NI',
					'ipphone' => $user['ipphone'][0] ?? 'NI',
					'title' => $user['title'][0] ?? 'NI',
					'department' => $user['department'][0] ?? 'NI',
					'company' => $user['company'][0] ?? 'NI',
					'groups' => $user['memberof'] ?? 'NI',
					'userAccountControl' => isset($user['useraccountcontrol']) ? ($user['useraccountcontrol'][0] == 512 ? true : false) : false,
					'accountexpires' => gettype($user['accountexpires']) == 'object' ? $user['accountexpires'][0] : 0,
				]);
			}

			$data = [
				'data' => $finaList,
				'total' => $users->total()
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
			];
			
			return $this->successResponse($data);
		} catch (Exception $e) {
			$this->logError("ERRO AO BUSCAR USUÁRIO POR CPF: $e");
		} catch (LdapRecordException $ex) {
			$this->logError("ERRO AO BUSCAR USUÁRIO POR CPF: " . $ex->getDetailedError());
		}
	}

	private function getOuQuery($listType)
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
