<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ApiResponseBuilder
{
    private bool $ok = true;

    private mixed $data = null;

    /**
     * @var callable|null
     */
    private $transformer = null;

    private ?string $message = null;

    private int $status = 200;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $errors = null;

    public function success(mixed $data = null, callable|array|string|null $transformer = null, int $status = 200): self
    {
        $this->ok = true;
        $this->data = $data;
        $this->transformer = $transformer;
        $this->status = $status;

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function error(string $message, int $status = 500, ?array $errors = null): self
    {
        $this->ok = false;
        $this->message = $message;
        $this->status = $status;
        $this->errors = $errors;

        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function status(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function respond(): JsonResponse
    {
        $payload = [
            'success' => $this->ok,
            'message' => $this->message,
        ];

        if ($this->ok) {
            $payload['data'] = $this->transform($this->data);

            if ($this->data instanceof LengthAwarePaginator) {
                $payload['meta'] = [
                    'current_page' => $this->data->currentPage(),
                    'last_page' => $this->data->lastPage(),
                    'per_page' => $this->data->perPage(),
                    'total' => $this->data->total(),
                ];
            }
        } else {
            $payload['errors'] = $this->errors;
        }

        return response()->json($payload, $this->status);
    }

    private function transform(mixed $data): mixed
    {
        if (! $this->transformer) {
            return $data;
        }

        if ($data instanceof LengthAwarePaginator) {
            return $data->getCollection()
                ->map(fn ($item) => $this->transformItem($item))
                ->values();
        }

        if ($data instanceof Collection || $data instanceof EloquentCollection) {
            return $data->map(fn ($item) => $this->transformItem($item))->values();
        }

        if ($data instanceof Model || is_array($data)) {
            return $this->transformItem($data);
        }

        return $data;
    }

    private function transformItem(mixed $item): mixed
    {
        return call_user_func($this->transformer, $item);
    }
}
