<?php

namespace App\Utils;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Termwind\Components\Raw;

use function Laravel\Prompts\select;

class Helpers
{
	public static function clearName($name)
	{
		return strtolower(
			str_replace(
				array(
					'', 'à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï',
					'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä',
					'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý'
				),
				array(
					'_', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o',
					'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I',
					'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y'
				),
				$name
			)
		);
	}

	public static function patternFormat(String $type): String
	{
		$formats = [
			'patternPhoneComplete' => '/^(?:(?:\+|00)?(55)\s?)?(?:(?:\(?[1-9][0-9]\)?)?\s?)?(?:((?:9\d|[2-9])\d{3})-?(\d{4}))$/',
			'patternCpfComplete' => '/^\d{3}\.\d{3}\.\d{3}\-\d{2}$/',
			'patternPhysicalDeliveryOfficeName' => '/^\d{10}/',
			'patternSerialNumber' => '/^\d{11}$/',
			'patternPhoneDigit' => '/^\d{4}$/',
			'patternCpf' => '/^\d{11}$/',
		];

		return $formats[$type];
	}

	public static function formatCnpjCpf($value): String
	{
		$CPF_LENGTH = 11;
		$cnpj_cpf = preg_replace("/\D/", '', $value);

		if (strlen($cnpj_cpf) === $CPF_LENGTH) {
			return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $cnpj_cpf);
		}

		return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $cnpj_cpf);
	}

	public static function filetimeToStr($filetime)
	{
		date_default_timezone_set("UTC");
		$resp = (int)($filetime / 10000000);
		$diff = 11644473600;
		$resp = $resp - $diff;
		$resp = date("Y-m-d H:i:s", $resp);
		return $resp;
	}

	public static function cryptSenha($senha)
	{
		try {			
			$senhaUtf = urlencode($senha);

			$query = "SELECT dbo.Crypt(?) AS senha";
        	$senhaCriptografada = DB::select($query, [$senhaUtf]);
			        	
			return $senhaCriptografada[0]->senha;
			
		} catch (Exception $e) {
			Log::warning("ERRO AO CRIPTOGRAFAR SENHA: " . $e);
		}
	}

	public static function filter_string_polyfill(string $string): string
	{
		$str = preg_replace('/\x00|<[^>]*>?/', '', $string);
		return str_replace(["'", '"'], ['&#39;', '&#34;'], $str);
	}
}
