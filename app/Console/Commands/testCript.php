<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class testCript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cript';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try{
			$senha =  DB::connection('sqlsrv_lyceum')->select("select dbo.Crypt('12323232') senha");

			dd($senha[0]->senha);
		}catch(Exception $e){
			Log::warning("ERRO AO CRIPTOGRAFAR SENHA: ". $e->getMessage());
		}
    }
}
