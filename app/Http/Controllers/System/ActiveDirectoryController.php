<?php

namespace App\Http\Controllers\System;

use Exception;
use App\Ldap\UserLdap;
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
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Exceptions\ConstraintViolationException;


class ActiveDirectoryController extends Controller
{
  use ApiResponser;

  private $connection;
  private $samaccountname;
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

    if($this->checkIfUserExists('name', $request)){
      return $this->errorResponse("Já existe um usuário criado com este nome");
    }

    if($this->checkIfUserExists('email', $request)){
      return $this->errorResponse("Este e-mail já se encontra cadastrado.");
    }

    $this->createSamAccountName($request);

    return $this->saveUser($request);
  }

  public function validarCampos(Request $request){
    $msgError = "";
    // $patternCpf = '/^\d{3}\.\d{3}\.\d{3}\-\d{2}$/';
    $patternCpf = '/^\d{11}/';
    // $patternPhone = '/^(?:(?:\+|00)?(55)\s?)?(?:(?:\(?[1-9][0-9]\)?)?\s?)?(?:((?:9\d|[2-9])\d{3})-?(\d{4}))$/';
    $patternPhone = '/^\d{4}/';

    if(!$request->givenname){
      return $msgError = "Campo givenname é obrigatório o preenchimento";
    }

    if(!$request->displayname){
      return $msgError = "Campo displayname é obrigatório o preenchimento";
    }

    if(!$request->cn){
      return $msgError = "Campo cn é obrigatório o preenchimento";
    }

    if(!strstr($request->cn, " ")){
      return $msgError = "Nome com Formato incorreto!";
    }

    if(!$request->sn){
      return $msgError = "Campo sn é obrigatório o preenchimento";
    }

    if(!$request->description){
      return $msgError = "Campo description é obrigatório o preenchimento";
    }elseif(!preg_match($patternCpf, $request->description)){
      return $msgError = "Formato description está incorreto";
    }

    if(!$request->physicaldeliveryofficename){
      return $msgError = "Campo physicaldeliveryofficename é obrigatório o preenchimento";
    }elseif(!preg_match($patternPhone, $request->physicaldeliveryofficename)){
        return $msgError = "Formato physicaldeliveryofficename está incorreto";
      }

    if(!filter_var($request->mail, FILTER_VALIDATE_EMAIL)){
      return $msgError = "E-mail inválido";
    }

    if(!$request->scriptpatch){
      return $msgError = "Campo scriptpatch é obrigatório o preenchimento";
    }

    if(!$request->maneger){
      return $msgError = "Campo maneger é obrigatório o preenchimento";
    }

    if(!$request->pager){
      return $msgError = "Campo pager é obrigatório o preenchimento";
    }elseif(!filter_var($request->pager, FILTER_VALIDATE_EMAIL)){
      return $msgError = "pager inválido";
    }

    if(!$request->title){
      return $msgError = "Campo title é obrigatório o preenchimento";
    }

    if(!$request->departament){
      return $msgError = "Campo departament é obrigatório o preenchimento";
    }

    if(!$request->company){
      return $msgError = "Campo company é obrigatório o preenchimento";
    }

    if(!$request->ipphone){
      return $msgError = "Campo ipphone é obrigatório o preenchimento";
    }elseif(!preg_match($patternPhone, $request->ipphone)){
      return $msgError = "Formato ipphone está incorreto";
    }

    return $msgError;
  }

  public function createSamAccountName(Request $request)
  {
    $name = strtolower(
							str_replace(
								array('', 'à','á','â','ã','ä', 'ç', 'è','é','ê','ë', 'ì','í','î','ï',
									'ñ', 'ò','ó','ô','õ','ö', 'ù','ú','û','ü', 'ý','ÿ', 'À','Á','Â','Ã','Ä',
									'Ç', 'È','É','Ê','Ë', 'Ì','Í','Î','Ï', 'Ñ', 'Ò','Ó','Ô','Õ','Ö', 'Ù','Ú','Û','Ü', 'Ý'),
									array('_', 'a','a','a','a','a', 'c', 'e','e','e','e', 'i','i','i','i', 'n', 'o','o','o',
									'o','o', 'u','u','u','u', 'y','y', 'A','A','A','A','A', 'C', 'E','E','E','E', 'I','I','I',
									'I', 'N', 'O','O','O','O','O', 'U','U','U','U', 'Y'),
									$request->cn));
    $names = explode(" ", $name);

    $firstName = strval($names[0]);

    foreach($names as $key => $name){
        if(strlen($name) < 3 || $key == 0){
            continue;
        }

        $secondName = strval($names[$key]);
        $this->samaccountname = $firstName.'.'.$secondName.strval($this->complementNumericSmaAccount == 0 ? '': $this->complementNumericSmaAccount);

        if ($this->checkIfUserExists('account', $request)){
          if(count($names) - 1 == $key){
            $this->complementNumericSmaAccount = random_int(1,99);
            $this->samaccountname = $firstName.'.'.$secondName.strval($this->complementNumericSmaAccount);
          }
          continue;
        }
        break;
    }
  }

  private function checkIfUserExists(string $type, Request $request = null){
    $check = [];

    switch($type){
      case 'account':
        $check = $this->connection->query()->where('samaccountname', '=', $this->samaccountname)->get();
      break;
      case 'name':
        $check = $this->connection->query()->where('cn', '=', $request->cn)->get();
      break;
      case 'email':
        $check = $this->connection->query()->where('mail', '=', $request->mail)->get();
      break;
    }
    return $check;
  }

  private function saveUser(Request $request){
    $user = (new User)->inside(self::CN);
    $user->cn = $request->cn;
    $user->unicodePwd = 'Faesa@2023';
    $user->sn = $request->cn;
    $user->mail = $request->mail;
    $user->samaccountname = $this->samaccountname;
    $user->userAccountControl = 512;
    $user->givenname = $request->givenname;
    $user->displayname = $request->displayname;
    $user->cn = $request->cn;
    $user->sn = $request->sn;
    $user->displayname = $request->displayname;
    $user->description = $request->description;
    $user->physicaldeliveryofficename = $request->physicaldeliveryofficename;
    $user->mail = $request->mail;
    $user->samaccountname = $this->samaccountname;
    $user->userAccountControl = 512;
    // $user->scriptpatch = $request->scriptpatch;
    $user->ipphone = $request->ipphone;
    $user->pager = $request->pager;
    $user->title = $request->title;
    // $user->departament = $request->departament;
    $user->company = $request->company;
    // $user->maneger = $request->maneger;
    // $user->memberof = ["CN=Wireless Alunos,OU=Grupos Servicos,OU=Servicos,DC=faesa,DC=br"];
    $user->proxyaddresses = $request->proxyaddresses;
    $user->unicodePwd = 'Faesa@2023';

    $group = Group::find('CN=Desenvolvimento,DC=faesa,DC=br');

    // $group = Group::find('cn=Accounting,dc=local,dc=com');

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
