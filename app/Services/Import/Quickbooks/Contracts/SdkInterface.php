<?php

namespace App\Services\Import\Quickbooks\Contracts;

interface SdkInterface
{
    function getAuthorizationUrl(): string;
    function accessToken(string $code, string $realm): array;
    function refreshToken(): array;
    function getAccessToken(): array;
    function getRefreshToken(): array;
    function totalRecords(string $entity): int;
    function fetchRecords(string $entity, int $max): array;
}
