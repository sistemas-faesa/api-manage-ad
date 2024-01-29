<?php

namespace App\Http\Requests\Auth;

use Carbon\Carbon;
use Adldap\Models\User;
use App\Models\UserLdap;
use LdapRecord\Container;
use Adldap\AdldapInterface;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Adldap\Laravel\Facades\Adldap;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use App\Ldap\UserLdap as LdapUserLdap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{

    use ApiResponser;

	CONST CN_LOGIN = 'OU=NTI,OU=ADMINISTRATIVO,OU=FUNCIONARIOS,OU=FAESA,DC=faesa,DC=br';

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
        $credentials = [
            'samaccountname' => $this->username,
            'password' => $this->password,
        ];

		$checkOuLogin = $this->checkOuLogin($this->username);

		if(!$checkOuLogin){
			RateLimiter::hit($this->throttleKey());
            return $this->errorResponse(['error' => 'Usuário não possui permissão para realizar o login']);
		}

        if (!auth('api')->attempt($credentials)) {
            RateLimiter::hit($this->throttleKey());
            return $this->errorResponse(['fail' => 'unauthenticated']);
        }else{
            return response()->json([
                'user_info' => auth('api')->user(),
                // 'access_token' => $token,
                // 'token_type' => 'Bearer',
                // 'expires_at' => Carbon::now()->addMinutes($minutes)->format('d-m-Y H:i:s'),
            ], 200);

            RateLimiter::clear($this->throttleKey());
        }

    }

	private function checkOuLogin($samaccountname){
		$connection = Container::getDefaultConnection();
		$check = $connection->query()->in(self::CN_LOGIN)->where('samaccountname', '=', $samaccountname)->get();
		dd($check);
		return $check;
	}

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
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
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }

    public function logout(){

        auth('api')->logout();

        return response()->json(['data' => 'Sessão encerrada com sucesso'], 200);

    }

    public function refresh(){

        $token = auth('api')->refresh();

        return response()->json(['token' => $token]);
    }

    public function me(){

        return response()->json(auth()->user());

    }
}
