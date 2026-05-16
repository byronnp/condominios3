<?php

namespace App\Http\Middleware;

use App\Support\ApiResponder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminAccess
{
    public function __construct(private readonly ApiResponder $responder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return $this->responder->error('No autorizado.', 403)->respond();
        }

        return $next($request);
    }
}
