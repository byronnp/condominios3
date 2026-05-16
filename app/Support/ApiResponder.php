<?php

namespace App\Support;

class ApiResponder
{
    public function success(mixed $data = null, callable|array|string|null $transformer = null, int $status = 200): ApiResponseBuilder
    {
        return (new ApiResponseBuilder())->success($data, $transformer, $status);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function error(string $message, int $status = 500, ?array $errors = null): ApiResponseBuilder
    {
        return (new ApiResponseBuilder())->error($message, $status, $errors);
    }
}
