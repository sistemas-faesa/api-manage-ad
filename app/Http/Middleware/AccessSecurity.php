<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccessSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $validSecrets = explode(',', env('ACCESS_SECRET'));

        if(in_array($request->header('Authorization'), $validSecrets))
        {
            return $next($request);
        }

        abort(Response::HTTP_UNAUTHORIZED);
    }
}
