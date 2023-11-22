<?php

namespace App\Http\Controllers\System;

use App\Ldap\UserLdap;
use LdapRecord\Container;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LdapRecord\LdapRecordException;
use App\Http\Controllers\Controller;
use App\Mail\ResetPassword;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use LdapRecord\Exceptions\ConstraintViolationException;
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Models\ActiveDirectory\User;


class ActiveDirectoryController extends Controller
{
  use ApiResponser;

  private $connection;
  private $samaccountname;
  private $cn;
  private $mail;
  private $complementNumericSmaAccount = 0;
  CONST CN = 'OU=Desenvolvimento,DC=faesa,DC=br';

  public function __construct(Container $connection) {
    $this->connection = $connection::getConnection('default');
  }

  public function validateSaveUser(Request $request)
  {
    if(strlen($this->validarCampos($request))>0){
      return $this->errorResponse($this->validarCampos($request), 400);
    }

    $this->cn = $request->cn;
    $this->mail = $request->mail;

    if($this->checkIfUserExists('name')){
      return $this->errorResponse("Já existe um usuário criado com este nome");
    }

    if($this->checkIfUserExists('email')){
      return $this->errorResponse("Este e-mail já se encontra cadastrado.");
    }

    $this->createSamAccountName();

    return $this->saveUser($request);
  }

  public function validarCampos(Request $request){
    $msgError = "";

    if(!$request->has('cn')){
      $msgError = "Campo cn é obrigatório";
    }elseif(!$request->cn){
      $msgError = "Campo cn é obrigatório o preenchimento";
    }

    if(!strstr($request->cn, " ")){
      $msgError = "Nome com Formato incorreto!";
    }

    if(!filter_var($request->mail, FILTER_VALIDATE_EMAIL)){
      $msgError = "E-mail inválido";
    }

    return $msgError;

  }

  public function createSamAccountName()
  {
    /*
        Regra de criação de usuário:
        primeiro e próximo nome (verificando se existe, se sim, ir para o próximo até que não exista.)
    */
    $name = strtolower(
							str_replace(
								array('', 'à','á','â','ã','ä', 'ç', 'è','é','ê','ë', 'ì','í','î','ï',
									'ñ', 'ò','ó','ô','õ','ö', 'ù','ú','û','ü', 'ý','ÿ', 'À','Á','Â','Ã','Ä',
									'Ç', 'È','É','Ê','Ë', 'Ì','Í','Î','Ï', 'Ñ', 'Ò','Ó','Ô','Õ','Ö', 'Ù','Ú','Û','Ü', 'Ý'),
									array('_', 'a','a','a','a','a', 'c', 'e','e','e','e', 'i','i','i','i', 'n', 'o','o','o',
									'o','o', 'u','u','u','u', 'y','y', 'A','A','A','A','A', 'C', 'E','E','E','E', 'I','I','I',
									'I', 'N', 'O','O','O','O','O', 'U','U','U','U', 'Y'),
									$this->cn));
    $names = explode(" ", $name);

    $firstName = strval($names[0]);

    foreach($names as $key => $name){
        if(strlen($name) < 3 || $key == 0){
            continue;
        }

        $secondName = strval($names[$key]);
        $this->samaccountname = $firstName.'.'.$secondName.strval($this->complementNumericSmaAccount == 0 ? '': $this->complementNumericSmaAccount);

        if ($this->checkIfUserExists('account')){
            if(count($names) - 1 == $key){
                $this->complementNumericSmaAccount = random_int(1,99);
                $this->samaccountname = $firstName.'.'.$secondName.strval($this->complementNumericSmaAccount);
            }
            continue;
        }
        break;
    }
  }

  private function checkIfUserExists($type){
    $check = [];

    switch($type){
      case 'account':
        $check = $this->connection->query()->where('samaccountname', '=', $this->samaccountname)->get();
      break;
      case 'name':
        $check = $this->connection->query()->where('cn', '=', $this->cn)->get();
      break;
      case 'email':
        $check = $this->connection->query()->where('mail', '=', $this->mail)->get();
      break;
    }

    return $check;
  }

  private function saveUser(Request $request){
    $user = (new User)->inside(self::CN);
    $user->cn = $request->cn;
    $user->unicodePwd = 'Faesa@2023';
    $user->sn = $request->cn;
    $user->company = 'faesa';
    $user->mail = $request->mail;
    $user->samaccountname = $this->samaccountname;
    $user->userAccountControl = 512;

    try {
      $user->save();
      $user->refresh();

      $date = Date('dd/mm/yyyy');
      Log::info("USUÁRIO CRIADO EM $date, $user");
      return $this->successResponse($user);

    }catch(Exception  $ex){
        Log::warning("ERRO AO CRIAR O USUÁRIO: CODE: $ex");
    }
  }

  public function changePassword($mail_user){
    try {
        $content = [
            'body' => 'Test',
            'token' =>random_bytes(4)
        ];

        $user = User::find('cn=Teste Teste da Silva Junior, OU=Desenvolvimento,DC=faesa,DC=br');

        $user->unicodepwd  = 'Faesa@202020';

        $user->save();
        $user->refresh();

        Mail::to(['junior.devstack@gmail.com'])->send(new ResetPassword($content));

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

  public function listAllUsers()
  {
    $users = UserLdap::paginate(5);

    return $this->successResponse($users);
  }

}
