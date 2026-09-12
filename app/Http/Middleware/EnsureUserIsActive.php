<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active && ! $request->routeIs('approval.pending', 'logout')) {
            if ($request->expectsJson()) {
                abort(403, 'Your account is awaiting approval.');
            }

            return redirect()->route('approval.pending');
        }

        return $next($request);
    }
}
