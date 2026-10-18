<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Business-rule failure that renders itself as a clean JSON error (Laravel calls render() automatically). */
class LuvuApiException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['error' => $this->errorCode, 'message' => $this->getMessage()], $this->status);
    }
}
