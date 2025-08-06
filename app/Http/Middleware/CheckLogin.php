<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckLogin
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('logged_in')) {
            return redirect()->route('rutaLogin')->with('error', 'Debes iniciar sesión para acceder a esta página');
        }

        return $next($request);
    }
}