<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait GeneratesSku
{
    protected function generateSku(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4));
        return $prefix . '-' . strtoupper(Str::random(6));
    }
}