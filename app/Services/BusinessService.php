<?php

namespace App\Services;

use App\Models\Business;

class BusinessService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function generateBusinessCode(string $businessName): string
    {
        $words = explode(' ', strtoupper(trim($businessName)));
        $initials = array_map(fn($word) => $word[0], $words);
        $abbreviation = implode('', $initials);

        if (strlen($abbreviation) < 2) {
            $abbreviation = strtoupper(substr($businessName, 0, 4));
        }

        do {
            $randomNumber = rand(1000, 9999);
            $code = $abbreviation . '-' . $randomNumber;
        } while (Business::where('code', $code)->exists());

        return $code;

    }
}
