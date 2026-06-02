<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\UserSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\JwtService;
use App\Support\ResourceActions;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class AuthController extends Controller
{
    public function __construct(private readonly JwtService $jwt)
    {
        parent::__construct();
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'identification_type_id' => ['required', 'exists:catalog_items,id'],
            'identification_number' => ['required', 'string', 'max:30', 'unique:users,identification_number'],
            'mobile_phone' => ['nullable', 'string', 'max:30'],
            'landline_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create([
            'name' => User::fullName($data['first_name'], $data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'identification_type_id' => $data['identification_type_id'],
            'identification_number' => $data['identification_number'],
            'mobile_phone' => $data['mobile_phone'] ?? null,
            'landline_phone' => $data['landline_phone'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_RESIDENT,
            'is_active' => true,
        ]);

        return $this->responder
            ->success($user->load(['identificationType', 'userRole']), [UserTransformer::class, 'transform'], 201)
            ->message('Usuario creado correctamente. Debe aceptar una invitacion o ser asignado por administracion para ver una casa.')
            ->respond();
    }

    public function login(Request $request, AuditLogger $audit): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son validas.'],
            ]);
        }

        $now = Carbon::now();
        $session = UserSession::query()->create([
            'user_id' => $user->id,
            'token_id' => (string) Str::uuid(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_in_at' => $now,
            'last_active_at' => $now,
        ]);

        $user->forceFill([
            'last_login_at' => $now,
            'last_active_at' => $now,
        ])->save();

        $audit->record(
            action: 'auth.login',
            module: 'auth',
            user: $user,
            entity: $session,
            description: 'Inicio de sesion correcto.',
            request: $request,
        );

        $token = $this->jwt->make($user, $session->token_id);

        return $this->responder->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl_minutes') * 60,
        ])->message('Login realizado correctamente.')
            ->respond()
            ->withCookie($this->authCookie($token));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->responder->success([
            'user' => UserTransformer::transform($request->user()->load(['identificationType', 'userRole'])),
            'session' => $this->sessionPayload($request->attributes->get('auth_session')),
            'actions' => ResourceActions::global($request->user()),
        ])->message('Usuario autenticado.')->respond();
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $session = $request->attributes->get('auth_session');
        $now = Carbon::now();

        if ($session instanceof UserSession) {
            $session->forceFill([
                'logged_out_at' => $now,
                'revoked_at' => $now,
                'last_active_at' => $now,
            ])->save();
        }

        $audit->record(
            action: 'auth.logout',
            module: 'auth',
            user: $request->user(),
            entity: $session instanceof UserSession ? $session : null,
            description: 'Sesion cerrada correctamente.',
            request: $request,
        );

        return $this->responder
            ->success()
            ->message('Sesion cerrada correctamente.')
            ->respond()
            ->withCookie(Cookie::forget(
                config('jwt.cookie.name'),
                config('jwt.cookie.path'),
                config('jwt.cookie.domain'),
            ));
    }

    private function authCookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            name: config('jwt.cookie.name'),
            value: $token,
            minutes: config('jwt.ttl_minutes'),
            path: config('jwt.cookie.path'),
            domain: config('jwt.cookie.domain'),
            secure: config('jwt.cookie.secure'),
            httpOnly: config('jwt.cookie.http_only'),
            raw: false,
            sameSite: config('jwt.cookie.same_site'),
        );
    }

    private function sessionPayload(?UserSession $session): ?array
    {
        if (! $session) {
            return null;
        }

        return [
            'id' => $session->id,
            'ip_address' => $session->ip_address,
            'user_agent' => $session->user_agent,
            'logged_in_at' => $session->logged_in_at,
            'last_active_at' => $session->last_active_at,
            'logged_out_at' => $session->logged_out_at,
            'revoked_at' => $session->revoked_at,
        ];
    }
}
