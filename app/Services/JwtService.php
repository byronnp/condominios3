<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class JwtService
{
    public function make(User $user, string $sessionToken): string
    {
        $now = Carbon::now();
        $payload = [
            'iss' => config('jwt.issuer'),
            'sub' => (string) $user->getKey(),
            'sid' => $sessionToken,
            'jti' => (string) Str::uuid(),
            'iat' => $now->timestamp,
            'exp' => $now->copy()->addMinutes(config('jwt.ttl_minutes'))->timestamp,
        ];

        return $this->encode($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Token JWT invalido.');
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $expectedSignature = $this->sign($encodedHeader.'.'.$encodedPayload);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException('Firma JWT invalida.');
        }

        $header = $this->jsonDecode($this->base64UrlDecode($encodedHeader));
        $payload = $this->jsonDecode($this->base64UrlDecode($encodedPayload));

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new InvalidArgumentException('Algoritmo JWT no soportado.');
        }

        if (! isset($payload['sub'], $payload['sid'], $payload['exp'])) {
            throw new InvalidArgumentException('Payload JWT incompleto.');
        }

        if ((int) $payload['exp'] < Carbon::now()->timestamp) {
            throw new InvalidArgumentException('Token JWT expirado.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        return $encodedHeader.'.'.$encodedPayload.'.'.$this->sign($encodedHeader.'.'.$encodedPayload);
    }

    private function sign(string $value): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $value, $this->secret(), true));
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET o APP_KEY no esta configurado.');
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Token JWT no es base64 valido.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonDecode(string $value): array
    {
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Token JWT no contiene JSON valido.');
        }

        return $decoded;
    }
}
