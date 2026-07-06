<?php

namespace App\Services\EDocument\Standards\France;

final class FranceEReportTaxCategory
{
    private function __construct()
    {
    }

    public static function normalize(?string $category): ?string
    {
        return match ($category) {
            'S' => 'standard',
            'K', 'AE' => 'reverse_charge',
            'E' => 'exempt',
            'Z' => 'zero_rated',
            'G' => 'export',
            'O' => 'outside_scope',
            default => $category,
        };
    }
}
