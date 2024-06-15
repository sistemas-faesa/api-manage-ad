<?php

namespace App\Console\Commands;

use App\Http\Controllers\System\GroupController;
use Exception;
use App\Utils\Helpers;
use LdapRecord\Container;
use App\Models\LogUsersAd;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LdapRecord\Models\ActiveDirectory\User;
use App\Http\Controllers\System\SearchController;

class AtualizarAluno extends Command
{
	private $connection;

	private $dataAluno;

	const EVENTO = 'ATUALIZA_STATUS_ALUNO';

	public function __construct()
	{
		parent::__construct();

		$this->connection = Container::getDefaultConnection();
		ini_set('memory_limit', '-1');
	}
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'app:atualizar-aluno';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Atualizar Status do aluno no AD';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		try {
			$alunos = DB::connection('sqlsrv_lyceum')->select("select * from VW_FAESA_ALUNOS_INTEGRACAO_AD_ATUALIZAR vw");
			if (count($alunos) > 0) {
				foreach ($alunos as $aluno) {
					$searchController = new SearchController($this->connection);

					$request = new Request(['cpf' => $aluno->cpf]);
					$resultSearch = $searchController->getUserByCpf($request);
					$content = $resultSearch->content();

					$result = json_decode($content, true);

					$this->dataAluno = $result['data'];

					$groupAluno = $this->dataAluno['groups'];

					if ($this->isContainedOtherGroups($groupAluno)) {
						$dataLog = [
							'nome' => $aluno->nome_exibicao,
							'cpf' => $aluno->cpf,
							'matricula' => $aluno->cpf,
							'login' => $this->dataAluno['samaccountname'],
							'evento' => self::EVENTO,
							'obs' => 'Este usuário pertence a outros grupos.',
							'status' => 'alerta',
						];

						$this->registerLogDb([$dataLog]);

						continue;
					}

					$this->atualizarStatus($aluno->cpf);
				}
			}
		} catch (Exception $e) {
			$msgErro = "Erro no processo de atualização de status no AD: " . $e->getMessage() . "Em: " . Date('d/m/Y');
			Log::warning($msgErro);
			Log::warning("Tipo de Exceção: " . get_class($e));
			Log::warning("Rastreamento: " . $e->getTraceAsString());

			$dataLog = [
				'nome' => $this->dataAluno['displayname'],
				'cpf' => $this->dataAluno['description'],
				'matricula' => $this->dataAluno['physicaldeliveryofficename'],
				'login' => $this->dataAluno['samaccountname'],
				'evento' => self::EVENTO,
				'obs' => $msgErro,
				'status' => 'erro',
			];

			$this->registerLogDb([$dataLog]);
		}
	}

	private function isContainedOtherGroups($groupsAluno): bool
	{
		$groupFuncionario = GroupController::FUNCIONARIOS;

		$groupProfessor = GroupController::PROFESSORES;

		foreach ($groupFuncionario as $func) {
			if (in_array($func, $groupsAluno)) {
				return true;
			}
		}

		foreach ($groupProfessor as $prof) {
			if (in_array($prof, $groupsAluno)) {
				return true;
			}
		}

		return false;
	}

	private function atualizarStatus($cpf)
	{
		try {
			$cpfMasked = Helpers::formatCnpjCpf($cpf);

			$userInfo = $this->connection->query()->whereIn('description', [$cpf, $cpfMasked])->get();

			$userCnFind = $userInfo[0]['dn'];

			$user = User::find($userCnFind);
			$user->userAccountControl = 512 + 2;

			$dataLog = [
				'nome' => $this->dataAluno['displayname'],
				'cpf' => $this->dataAluno['description'],
				'matricula' => $this->dataAluno['physicaldeliveryofficename'],
				'login' => $this->dataAluno['samaccountname'],
				'evento' => self::EVENTO,
				'obs' => "Conta desativada com sucesso no AD. Data: " .  Date('d/m/Y'),
				'status' => 'exito',
			];

			$this->registerLogDb([$dataLog]);

			$user->save();

			return;
		} catch (Exception  $ex) {
			Log::warning("ERRO JOB AO ALTERAR STATUS USUÁRIO: CODE: $ex");
		}
	}

	private function registerLogDb($data)
	{
		$data = $data[0];

		try {
			$log = LogUsersAd::create([
				'nome' => $data['nome'],
				'cpf' => $data['cpf'],
				'matricula' => $data['matricula'],
				'login' => $data['login'],
				'evento' => $data['evento'],
				'obs' => $data['obs'],
				'status' => $data['status']
			]);

			return $log;
		} catch (Exception $e) {
			Log::warning("ERRO NO PROCESSO DE SALVAR LOG, JOB ATUALIZAR STATUS DO ALUNO: " . $e->getMessage());
			Log::warning("Tipo de Exceção: " . get_class($e));
			Log::warning("Rastreamento: " . $e->getTraceAsString());
		}
	}
}
