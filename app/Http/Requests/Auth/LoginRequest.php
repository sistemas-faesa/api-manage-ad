<?php

namespace App\Http\Requests\Auth;

use App\Utils\Helpers;
use LdapRecord\Container;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use LdapRecord\Models\ActiveDirectory\Group;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{

	use ApiResponser;

	const CN_LOGIN = 'CN=Nucleo_de_Tecnologia_da_Informação,OU=Grupos Setores,OU=Servicos,DC=faesa,DC=br';

	/**
	 * Determine if the user is authorized to make this request.
	 */
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * Attempt to authenticate the request's credentials.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function authenticate()
	{
		if (!$this->username) {
			$msgError = "Campo username é obrigado!";
			return $this->errorResponse($msgError , 400);
		}

		if (!$this->password) {
			$msgError = "Campo password é obrigatório";
			return $this->errorResponse($msgError , 400);
		}

		$userName = Helpers::filter_string_polyfill($this->username);
		$password = Helpers::filter_string_polyfill($this->password);
		
		$credentials = [
			'samaccountname' => $this->username,
			'password' => $this->password,
		];
				
		$checkOuLogin = $this->checkOuLogin($this->username);

		if (!$checkOuLogin) {
			RateLimiter::hit($this->throttleKey());
			return $this->errorResponse('Usuário não possui permissão para realizar o login');
		}

		if (!$token = auth('api')->attempt($credentials)) {
			RateLimiter::hit($this->throttleKey());
			return $this->errorResponse('unauthenticated');
		} else {
			$ldapUser = auth('api')->user();
			
			if ($token = JWTAuth::getToken()) {
				JWTAuth::invalidate($token);
			}

			$token = JWTAuth::fromUser($ldapUser);

			return response()->json([
				'access_token' => $token,
			], 200);

			RateLimiter::clear($this->throttleKey());
		}
	}

	private function checkOuLogin($samaccountname)
	{
		$connection = Container::getDefaultConnection();

		$group = Group::find(self::CN_LOGIN);

		$checkUser = $group->members()->whereEndsWith('samaccountname', $samaccountname)->get();
		$checkUser = $checkUser->toArray();

		return $checkUser;
	}

	/**
	 * Ensure the login request is not rate limited.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function ensureIsNotRateLimited(): void
	{
		if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
			return;
		}

		event(new Lockout($this));

		$seconds = RateLimiter::availableIn($this->throttleKey());

		throw ValidationException::withMessages([
			'email' => trans('auth.throttle', [
				'seconds' => $seconds,
				'minutes' => ceil($seconds / 60),
			]),
		]);
	}

	/**
	 * Get the rate limiting throttle key for the request.
	 */
	public function throttleKey(): string
	{
		return Str::transliterate(Str::lower($this->input('email')) . '|' . $this->ip());
	}

	public function logout()
	{

		auth('api')->logout();

		return response()->json(['data' => 'Sessão encerrada com sucesso'], 200);
	}

	public function refresh()
	{
		$token = auth('api')->refresh();

		return response()->json(['token' => $token]);
	}

	public function me()
	{
		return response()->json(auth('api')->user());
	}
}
