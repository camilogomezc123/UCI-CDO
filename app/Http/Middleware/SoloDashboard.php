<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SoloDashboard
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->check() && auth()->user()->rol === 'visual') {
            if (!$request->routeIs('dashboard')) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
