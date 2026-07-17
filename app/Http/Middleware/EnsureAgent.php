<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->is_admin, 403);

        return $next($request);
    }
}
