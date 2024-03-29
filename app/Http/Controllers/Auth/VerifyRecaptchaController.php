<?php

namespace App\Http\Controllers\Auth;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class VerifyRecaptchaController extends Controller
{
	public static function verifyToken(Request $request)
	{
		$data = [];

		$response = $request->input('g-recaptcha-response');
		$client = new Client();
		$secret = env('RECAPTCHA_SECRET');
		
		$response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
			'form_params' => [
				'secret' => $secret,
				'response' => $response,
			],
		]);

		$body = json_decode((string) $response->getBody());

		if (!$body->success) {
			$data = [
				'status' => 'error',
				'reCAPTCHA' => 'Erro de validação reCAPTCHA'
			];
		} else {
			$data = [
				'status' => 'success',
				'reCAPTCHA' => 'Mensagem enviada com sucesso!'
			];
		}
		return $data;
	}
}
