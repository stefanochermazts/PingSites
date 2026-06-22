<?php

namespace App\DTOs;

use App\Enums\ErrorType;

readonly class CheckResult
{
    public function __construct(
        public bool $success,
        public ?int $httpCode = null,
        public ?int $responseTimeMs = null,
        public ?ErrorType $errorType = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(int $httpCode, int $responseTimeMs): self
    {
        return new self(
            success: true,
            httpCode: $httpCode,
            responseTimeMs: $responseTimeMs,
        );
    }

    public static function failure(ErrorType $errorType, ?string $message = null, ?int $httpCode = null, ?int $responseTimeMs = null): self
    {
        return new self(
            success: false,
            httpCode: $httpCode,
            responseTimeMs: $responseTimeMs,
            errorType: $errorType,
            errorMessage: $message,
        );
    }
}
