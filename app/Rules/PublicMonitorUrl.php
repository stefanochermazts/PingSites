<?php

namespace App\Rules;

use App\Support\SsrfGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicMonitorUrl implements ValidationRule
{
    public function __construct(
        private readonly SsrfGuard $ssrfGuard = new SsrfGuard,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('URL obbligatorio.');

            return;
        }

        try {
            $this->ssrfGuard->validateUrl($value);
        } catch (\InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
