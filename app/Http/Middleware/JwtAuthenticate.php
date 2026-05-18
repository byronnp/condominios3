<?php

namespace App\Http\Middleware;

use App\Models\Auth\UserSession;
use App\Models\User;
use App\Services\JwtService;
use App\Support\ApiResponder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtAuthenticate
{
    public function __construct(private readonly JwtService $jwt, private readonly ApiResponder $responder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->cookie(config('jwt.cookie.name'));

        if (! $token) {
            return $this->responder->error('Token no enviado.', 401)->respond();
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (Throwable $exception) {
            return $this->responder->error($exception->getMessage(), 401)->respond();
        }

        $user = User::query()
            ->whereKey($payload['sub'])
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return $this->responder->error('Usuario no autorizado.', 401)->respond();
        }

        $session = UserSession::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('token_id', $payload['sid'])
            ->first();

        if (! $session) {
            return $this->responder->error('Sesion no activa.', 401)->respond();
        }

        $now = Carbon::now();

        $session->forceFill(['last_active_at' => $now])->save();
        $user->forceFill(['last_active_at' => $now])->save();

        Auth::setUser($user);
        $request->attributes->set('auth_session', $session);
        $request->attributes->set('jwt_payload', $payload);

        return $next($request);
    }
}
