<?php

namespace App\Services\Import\Quickbooks\Contracts;

interface SdkInterface
{
    public function getAuthorizationUrl(): string;
    public function accessToken(string $code, string $realm): array;
    public function refreshToken(): array;
    public function getAccessToken();
    public function getRefreshToken(): array;
    public function totalRecords(string $entity): int;
    public function fetchRecords(string $entity, int $max): array;
}
