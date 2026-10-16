<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! in_array($user->role->value, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Akses ditolak. Akun Anda ('.$user->role->label().') tidak memiliki izin untuk mengakses modul ini.');
        }

        return $next($request);
    }
}
