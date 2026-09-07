<?php

namespace App\Services\Wordpress;

use Illuminate\Support\Str;

class WordpressTheme
{
    public function __construct(
        public readonly string $slug,
        public readonly ?string $name = null,
        public readonly ?string $stylesheetUrl = null,
    ) {}

    public function withName(?string $name): self
    {
        $name = is_string($name) ? trim($name) : '';

        return new self(
            $this->slug,
            $name !== '' ? $name : $this->name,
            $this->stylesheetUrl,
        );
    }

    public function displayName(): string
    {
        if (is_string($this->name) && $this->name !== '') {
            return $this->name;
        }

        return Str::headline($this->slug);
    }
}
