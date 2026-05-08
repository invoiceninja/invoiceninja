<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

use App\Rules\SafeExternalUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches user-supplied image URLs and returns them as data: URIs so the PDF
 * renderer (Chromium/Snappdf) never makes an outbound request for untrusted
 * content.
 *
 * Containment model:
 *   1. Re-validate URL shape + DNS via {@see SafeExternalUrl::check()}.
 *   2. GET with redirects disabled — any 3xx is a hard reject.
 *      rebind window between validate and fetch).
 *   3. Verify Content-Type + magic bytes; oversized or wrong-type payloads
 *      are rejected.
 *   4. Base64-encode and return data:image/...;base64,...
 *   5. Cache positive results so repeat renders don't refetch.
 */
class ImageFetcher
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const CACHE_TTL_SECONDS = 3600;

    private const CONNECT_TIMEOUT = 3;

    private const TIMEOUT = 10;

    /**
     * @param string|null $scope Tenant scope (typically company_key) used to
     *                           namespace cache keys so companies don't share
     *                           cached image bytes.
     */
    public function __construct(private ?string $scope = null) {}

    /**
     * Return a data: URI for the given URL, or null if the URL cannot be
     * safely fetched. Never throws.
     */
    public function inline(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'data:')) {
            return $this->isValidImageDataUri($url) ? $url : null;
        }

        $cacheKey = $this->cacheKey($url);
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $validated = SafeExternalUrl::check($url);
        if (!$validated['ok']) {
            return null;
        }

        $fetched = $this->fetch($validated);
        if ($fetched === null) {
            return null;
        }

        [$mime, $bytes] = $fetched;
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($bytes);

        Cache::put($cacheKey, $dataUri, self::CACHE_TTL_SECONDS);

        return $dataUri;
    }

    /**
     * @param array{ok:bool, url?:string} $validated
     * @return array{0:string, 1:string}|null  [mime, bytes]
     */
    private function fetch(array $validated): ?array
    {
        $url = $validated['url'] ?? '';
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::TIMEOUT)
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => true,
                ])
                ->get($url);
        } catch (ConnectionException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }

        $status = $response->status();
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $body = (string) $response->body();
        $length = strlen($body);
        if ($length === 0 || $length > self::MAX_BYTES) {
            return null;
        }

        $mime = $this->detectImageMime($body);
        if ($mime === null) {
            return null;
        }

        $headerType = strtolower((string) $response->header('Content-Type'));
        if ($headerType !== '' && !str_starts_with($headerType, 'image/')) {
            return null;
        }

        return [$mime, $body];
    }

    /**
     * Validate a data:image/... URI: supported subtype, valid base64,
     * decoded size within budget. Data URIs are not a network concern;
     * this check enforces shape and size only.
     */
    private function isValidImageDataUri(string $url): bool
    {
        if (!preg_match('#^data:image/(png|jpeg|jpg|gif|webp);base64,([A-Za-z0-9+/=]+)$#i', $url, $m)) {
            return false;
        }

        $decoded = base64_decode($m[2], true);
        if ($decoded === false) {
            return false;
        }

        return strlen($decoded) <= self::MAX_BYTES;
    }

    /**
     * Detect image MIME from the body's magic bytes. Returns null if the
     * bytes do not match a supported image format.
     */
    private function detectImageMime(string $body): ?string
    {
        if (str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')) {
            return 'image/gif';
        }

        if (strlen($body) >= 12
            && str_starts_with($body, 'RIFF')
            && substr($body, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    private function cacheKey(string $url): string
    {
        $scope = $this->scope !== null ? $this->scope . '.' : '';

        return 'pdf.image.' . $scope . hash('sha256', $url);
    }
}
