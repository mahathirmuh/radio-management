<?php

namespace App\Helpers\Cores;

use Illuminate\Http\JsonResponse as HttpJsonResponse;

/**
 * Fluent builder for the { status, message, data, errors } envelope used by the
 * Approval API controllers:
 *
 *   return JsonResponse::status(true)->message('Saved.')->data($row->toArray())->code(201)->send();
 */
class JsonResponse
{
    private string $message = '';

    private mixed $data = null;

    private mixed $errors = null;

    private int $code = 200;

    private function __construct(private bool $status)
    {
    }

    public static function status(bool $status): self
    {
        return new self($status);
    }

    public function message(?string $message): self
    {
        $this->message = (string) $message;

        return $this;
    }

    public function data(mixed $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function errors(mixed $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    public function code(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function send(): HttpJsonResponse
    {
        $body = [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
        ];
        if ($this->errors !== null) {
            $body['errors'] = $this->errors;
        }

        return response()->json($body, $this->code);
    }
}
