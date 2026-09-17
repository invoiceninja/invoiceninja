<?php

namespace App\Services\EDocument\Standards\France;

final class FranceEReportTaxCategory
{
    private const SUPPORTED = [
        'standard',
        'zero_rated',
        'reverse_charge',
        'intra_community',
        'exempt',
        'export',
        'outside_scope',
    ];

    private function __construct()
    {
    }

    public static function normalize(?string $category): ?string
    {
        return match ($category) {
            'S' => 'standard',
            'K' => 'intra_community',
            'AE' => 'reverse_charge',
            'E' => 'exempt',
            'Z' => 'zero_rated',
            'G' => 'export',
            'O' => 'outside_scope',
            default => $category,
        };
    }

    public static function isSupported(?string $category): bool
    {
        return in_array(self::normalize($category), self::SUPPORTED, true);
    }
}
