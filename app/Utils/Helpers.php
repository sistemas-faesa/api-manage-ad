<?php
namespace App\Utils;

class Helpers{
  public static function clearName($name){
    return strtolower(
      str_replace(
        array('', 'à','á','â','ã','ä', 'ç', 'è','é','ê','ë', 'ì','í','î','ï',
            'ñ', 'ò','ó','ô','õ','ö', 'ù','ú','û','ü', 'ý','ÿ', 'À','Á','Â','Ã','Ä',
            'Ç', 'È','É','Ê','Ë', 'Ì','Í','Î','Ï', 'Ñ', 'Ò','Ó','Ô','Õ','Ö', 'Ù','Ú','Û','Ü', 'Ý'),
            array('_', 'a','a','a','a','a', 'c', 'e','e','e','e', 'i','i','i','i', 'n', 'o','o','o',
            'o','o', 'u','u','u','u', 'y','y', 'A','A','A','A','A', 'C', 'E','E','E','E', 'I','I','I',
            'I', 'N', 'O','O','O','O','O', 'U','U','U','U', 'Y'),
            $name));
  }

  public static function patternFormat(String $type): String{
    $formats = [
        'patternPhoneComplete' => '/^(?:(?:\+|00)?(55)\s?)?(?:(?:\(?[1-9][0-9]\)?)?\s?)?(?:((?:9\d|[2-9])\d{3})-?(\d{4}))$/',
        'patternCpfComplete' => '/^\d{3}\.\d{3}\.\d{3}\-\d{2}$/',
        'patternPhysicalDeliveryOfficeName' => '/^\d{7}/',
        'patternSerialNumber' => '/^\d{11}$/',
        'patternPhoneDigit' => '/^\d{4}$/',
        'patternCpf' => '/^\d{11}$/',
    ];

    return $formats[$type];
  }
}
