<?php

namespace App\Console\Commands;


use Exception;
use LdapRecord\Container;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\System\ActiveDirectoryController;
use App\Http\Controllers\System\SearchController;
use App\Models\LogUsersAd;
use Illuminate\Http\Request;

class ImportAlunosAd extends Command
{
	private $connection;

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
	protected $signature = 'app:import-alunos-ad';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Comando responsável por importar os alunos ingressantes para AD';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		try {
			$alunos = DB::connection('sqlsrv_lyceum')->select("select * from VW_FAESA_ALUNOS_INTEGRACAO_AD vw
			where not EXISTS (select * from LYCEUM_INTEGRACAO.dbo.log_users_ads log_mig where log_mig.cpf = vw.cpf and status in ('existente', 'exito'))");

			if (count($alunos) > 0) {
				foreach ($alunos as $aluno) {

					$activeDirectory = new ActiveDirectoryController($this->connection);

					$importAluno = $this->checkImportAd($aluno->cpf);

					if (!$importAluno) {
						Log::warning("ALUNO JÁ MIGRADO ANTERIORMENTE: " . $aluno->cpf);
						continue;
					}

					$request = new Request(
						[
							'cn' => $aluno->nome_exibicao,
							'description' => $aluno->cpf,
							'physicaldeliveryofficename' => $aluno->matricula,
							'mail' => $aluno->email,
							'scriptpath' => 'ScriptLogon.vbsg',
							'pager' => $aluno->pager,
							'title' => 'ScriptLogon.vbsg',
							'company' => $aluno->empresa,
							'department' => $aluno->departamento,
							'ipphone' => 1234,
							'userType' => 'aluno',
						]
					);

					$response = $activeDirectory->validateSaveUser($request);

					$content = $response->content();

					$result = json_decode($content, true);

					if ($response->status() != 200) {
						$data = [
							'nome' => $aluno->nome_exibicao,
							'cpf' =>  $aluno->cpf,
							'matricula' => $aluno->matricula,
							'login' => '',
							'obs' => $result['error'],
							'status' => 'erro',
						];

						$this->registerLogDb($data);
						continue;
					}

					if ($response->status() == 200) {

						$data = [
							'nome' => $aluno->nome_exibicao,
							'cpf' =>  $aluno->cpf,
							'matricula' => $aluno->matricula,
							'login' => $result['data']['user']['samaccountname'][0],
							'obs' => 'Usuário criado com sucesso no AD',
							'status' => 'exito',
						];

						$this->registerLogDb($data);
						continue;
					}
				}

				$date = Date('d/m/Y');
				Log::warning("Migração finalizada em: $date");
				echo "Migração finalizada em: $date";

			} else {
				Log::warning("Não há aluno(s) para nmigrar");
				echo "Não há aluno(s) para nmigrar" ;
			}
		} catch (Exception $e) {
			Log::warning("ERRO NO PROCESSO DE IMPORTAÇÃO DE ALUNOS PARA O AD: " . $e->getMessage());
			Log::warning("Tipo de Exceção: " . get_class($e));
			Log::warning("Rastreamento: " . $e->getTraceAsString());

            $this->registerLogDb([
                'nome' => '',
                'cpf' => '',
                'matricula' => '',
                'login' => '',
                'obs' => "ERRO 001 NO PROCESSO DE IMPORTAÇÃO DE ALUNOS PARA O AD: " . $e->getMessage(),
                'status' => 'error',
            ]);
		}
	}

	private function checkImportAd($cpf)
	{
		$searchController = new SearchController($this->connection);
		try {
			$check = true;

			$request = new Request(['cpf' => $cpf]);
			$resultSearch = $searchController->getUserByCpf($request);
			$content = $resultSearch->content();
			$result = json_decode($content, true);

			if ($resultSearch->status() == 200) {
				if (count($result['data']) > 0) {
					$data = [
						'nome' => $result['data']['displayname'],
						'cpf' => $cpf,
						'matricula' => $result['data']['physicaldeliveryofficename'],
						'login' => $result['data']['samaccountname'],
						'obs' => 'Aluno já possui registro no AD, busca realizada por CPF.',
						'status' => 'existente',
					];

					$this->registerLogDb($data);

					return false;
				}
			}

			return $check;
		} catch (Exception $e) {
			Log::warning("ERRO NO PROCESSO DE CHECKAGEM DE ALUNO MIGRADO NO AD: " . $e->getMessage());
			Log::warning("Tipo de Exceção: " . get_class($e));
			Log::warning("Rastreamento: " . $e->getTraceAsString());

            $this->registerLogDb([
                'nome' => '',
                'cpf' => '',
                'matricula' => '',
                'login' => '',
                'obs' => "ERRO 002 NO PROCESSO DE IMPORTAÇÃO DE ALUNOS PARA O AD: " . $e->getMessage(),
                'status' => 'error',
            ]);
		}
	}

	private function registerLogDb($data)
	{

		try {
			$log = LogUsersAd::create([
				'nome' => $data['nome'],
				'cpf' => $data['cpf'],
				'matricula' => $data['matricula'],
				'login' => $data['login'],
				'evento' => 'IMPORT_AD',
				'obs' => $data['obs'],
				'status' => $data['status']
			]);

			return $log;

		} catch (Exception $e) {
			Log::warning("ERRO NO PROCESSO DE CHECKAGEM DE ALUNO MIGRADO NO AD: " . $e->getMessage());
			Log::warning("Tipo de Exceção: " . get_class($e));
			Log::warning("Rastreamento: " . $e->getTraceAsString());
		}
	}
}
