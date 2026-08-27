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

namespace App\Http\Controllers;

use App\Http\Requests\License\CheckRequest;
use App\Models\Account;
use App\Services\License\WhiteLabelRenewalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

class LicenseController extends BaseController
{
    /**
     * Claim a white label license.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/claim_license",
     *      operationId="getClaimLicense",
     *      tags={"claim_license"},
     *      summary="Attempts to claim a white label license",
     *      description="Attempts to claim a white label license",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="license_key",
     *          in="query",
     *          description="The license hash",
     *          example="d87sh-s755s-s7d76-sdsd8",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="product_id",
     *          in="query",
     *          description="The ID of the product purchased.",
     *          example="1",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success!",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function index(): JsonResponse
    {
        $this->checkLicense();

        $license_key = trim((string) request()->input('license_key', ''));
        $environment = (string) config('ninja.environment');

        Log::info('White label license: claim requested', [
            'step' => 'start',
            'environment' => $environment,
            'has_license_key' => $license_key !== '',
        ]);

        if ($environment !== 'selfhost') {
            Log::warning('White label license: claim rejected because environment is not selfhost', [
                'step' => 'environment',
                'environment' => $environment,
                'reachable' => null,
                'status_code' => null,
            ]);

            return $this->licenseFailure(
                ctrans('texts.invoice_license_or_environment', ['environment' => $environment]),
                400,
                $this->hostedLicenseUrl(),
                null,
                null,
                'environment',
            );
        }

        if ($license_key === '') {
            Log::warning('White label license: claim rejected because no license key was provided', [
                'step' => 'license_key',
                'reachable' => null,
                'status_code' => null,
            ]);

            return $this->licenseFailure(
                'A license key is required to activate a white label license.',
                400,
                $this->hostedLicenseUrl(),
                null,
                null,
                'license_key',
            );
        }

        $product_id = 3;

        if (substr($license_key, 0, 3) === 'v5_') {
            return $this->v5ClaimLicense($license_key, $product_id);
        }

        return $this->legacyClaimLicense($license_key, $product_id);
    }

    public function v5ClaimLicense(string $license_key, int $product_id = 3): JsonResponse
    {
        $this->checkLicense();

        if (config('ninja.environment') !== 'selfhost') {
            Log::warning('White label license: v5 claim rejected because environment is not selfhost', [
                'step' => 'environment',
                'environment' => config('ninja.environment'),
                'reachable' => null,
                'status_code' => null,
            ]);

            return $this->licenseFailure(
                ctrans('texts.invoice_license_or_environment', ['environment' => config('ninja.environment')]),
                400,
                $this->hostedLicenseUrl(),
                null,
                null,
                'environment',
            );
        }

        if (config('ninja.license_key')) {
            Log::info('White label license: using LICENSE_KEY from environment instead of the request value');
            $license_key = (string) config('ninja.license_key');
        }

        $url = $this->hostedLicenseUrl() . '/claim_license';
        $response = $this->contactLicenseServer($url, $license_key, function () use ($url, $license_key, $product_id) {
            return Http::timeout(15)
                ->connectTimeout(10)
                ->get($url, [
                    'license_key' => $license_key,
                    'product_id' => $product_id,
                ]);
        });

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $status_code = $response->status();
        $payload = $response->json();

        if (! $response->successful()) {
            $detail = is_array($payload) && isset($payload['message']) && is_string($payload['message'])
                ? $payload['message']
                : trans('texts.white_label_license_error');

            return $this->licenseFailure(
                $this->httpErrorMessage($url, $status_code, $detail),
                400,
                $url,
                true,
                $status_code,
                'http_response',
            );
        }

        if (! is_array($payload) || ! isset($payload['expires'])) {
            Log::warning('White label license: license server returned an unreadable payload', [
                'step' => 'parse',
                'license_server_url' => $url,
                'reachable' => true,
                'status_code' => $status_code,
            ]);

            return $this->licenseFailure(
                $this->emptyResponseMessage($url, $status_code),
                400,
                $url,
                true,
                $status_code,
                'parse',
            );
        }

        $account = auth()->user()->account;
        $expires = Carbon::parse($payload['expires'])->format('Y-m-d');

        $account->plan_term = Account::PLAN_TERM_YEARLY;
        $account->plan_expires = $expires;
        $account->plan = Account::PLAN_WHITE_LABEL;
        $account->save();

        Log::info('White label license: claimed successfully', [
            'step' => 'apply',
            'license_server_url' => $url,
            'reachable' => true,
            'status_code' => $status_code,
            'expires' => $expires,
        ]);

        return $this->licenseSuccess(trans('texts.bought_white_label'), $url, $status_code, 'apply');
    }

    private function legacyClaimLicense(string $license_key, int $product_id): JsonResponse
    {
        $url = rtrim((string) config('ninja.license_url'), '/') . '/claim_license';
        $response = $this->contactLicenseServer($url, $license_key, function () use ($url, $license_key, $product_id) {
            return Http::timeout(15)
                ->connectTimeout(10)
                ->get($url, [
                    'license_key' => $license_key,
                    'product_id' => $product_id,
                    'get_date' => 'true',
                ]);
        });

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $status_code = $response->status();
        $data = trim((string) $response->body());

        if (! $response->successful()) {
            return $this->licenseFailure(
                $this->httpErrorMessage($url, $status_code, trans('texts.white_label_license_error')),
                400,
                $url,
                true,
                $status_code,
                'http_response',
            );
        }

        if ($data === Account::RESULT_FAILURE) {
            Log::warning('White label license: license server rejected the key', [
                'step' => 'http_response',
                'license_server_url' => $url,
                'reachable' => true,
                'status_code' => $status_code,
            ]);

            return $this->licenseFailure(trans('texts.invalid_white_label_license'), 400, $url, true, $status_code, 'http_response');
        }

        if ($data === '') {
            Log::warning('White label license: license server returned an empty body', [
                'step' => 'parse',
                'license_server_url' => $url,
                'reachable' => true,
                'status_code' => $status_code,
            ]);

            return $this->licenseFailure(
                $this->emptyResponseMessage($url, $status_code),
                400,
                $url,
                true,
                $status_code,
                'parse',
            );
        }

        $date = date_create($data);

        if ($date === false) {
            Log::warning('White label license: license server returned an unreadable expiry date', [
                'step' => 'parse',
                'license_server_url' => $url,
                'reachable' => true,
                'status_code' => $status_code,
            ]);

            return $this->licenseFailure(
                $this->emptyResponseMessage($url, $status_code),
                400,
                $url,
                true,
                $status_code,
                'parse',
            );
        }

        $account = auth()->user()->account;

        if ($date < date_create()) {
            $account->plan_term = Account::PLAN_TERM_YEARLY;
            $account->plan_paid = null;
            $account->plan_expires = null;
            $account->plan = Account::PLAN_FREE;
            $account->save();

            Log::warning('White label license: claimed key is expired', [
                'step' => 'apply',
                'license_server_url' => $url,
                'reachable' => true,
                'status_code' => $status_code,
                'expires' => $date->format('Y-m-d'),
            ]);

            return $this->licenseFailure(trans('texts.expired_white_label'), 400, $url, true, $status_code, 'apply');
        }

        $account->plan_term = Account::PLAN_TERM_YEARLY;
        $account->plan_paid = $data;
        $account->plan_expires = $date->format('Y-m-d');
        $account->plan = Account::PLAN_WHITE_LABEL;
        $account->save();

        Log::info('White label license: claimed successfully', [
            'step' => 'apply',
            'license_server_url' => $url,
            'reachable' => true,
            'status_code' => $status_code,
            'expires' => $date->format('Y-m-d'),
        ]);

        return $this->licenseSuccess(trans('texts.bought_white_label'), $url, $status_code, 'apply');
    }

    private function checkLicense(): void
    {
        $account = auth()->user()->account;

        if ($account->plan == 'white_label' && $account->plan_expires && Carbon::parse($account->plan_expires)->lt(now())) {
            $result = (new WhiteLabelRenewalService())->checkAndRenew($account);

            if ($result === false) {
                $account->plan = null;
                $account->plan_expires = null;
                $account->save();
            }
        }
    }

    public function check(CheckRequest $request): Response|JsonResponse
    {
        $url = $this->hostedLicenseUrl() . '/api/check/whitelabel';

        if (! config('ninja.license_key')) {
            Log::warning('White label license: check skipped because LICENSE_KEY is not set', [
                'step' => 'license_key',
                'reachable' => null,
                'status_code' => null,
            ]);

            return $this->licenseFailure(
                ctrans('texts.white_label_license_not_present'),
                422,
                $url,
                null,
                null,
                'license_key',
            );
        }

        $license_key = (string) config('ninja.license_key');
        $response = $this->contactLicenseServer($url, $license_key, function () use ($url, $license_key) {
            return Http::timeout(15)
                ->connectTimeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'license' => $license_key,
                ]);
        }, 422);

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $status_code = $response->status();

        if ($response->successful()) {
            $payload = $response->json();
            $payload = is_array($payload) ? $payload : [];
            $payload['context'] = $this->licenseContext($url, true, $status_code, 'http_response');

            return response()->json($payload);
        }

        return $this->licenseFailure(
            $response->json('message') ?: $this->httpErrorMessage($url, $status_code, trans('texts.white_label_license_error')),
            422,
            $url,
            true,
            $status_code,
            'http_response',
        );
    }

    /**
     * @param  callable(): \Illuminate\Http\Client\Response  $request
     */
    private function contactLicenseServer(string $url, string $license_key, callable $request, int $failure_status = 400): \Illuminate\Http\Client\Response|JsonResponse
    {
        Log::info('White label license: contacting license server', [
            'step' => 'connect',
            'license_server_url' => $url,
            'reachable' => null,
            'status_code' => null,
        ]);

        try {
            $response = $request();
        } catch (Throwable $e) {
            $error = $this->redactLicenseKey($e->getMessage(), $license_key);

            Log::error('White label license: license server unreachable', [
                'step' => 'connect',
                'license_server_url' => $url,
                'reachable' => false,
                'status_code' => null,
                'error' => $error,
            ]);

            return $this->licenseFailure(
                "Unable to reach the license server at {$url}. This Invoice Ninja instance could not establish an outbound HTTPS connection. Check DNS, firewall, and proxy settings. Details: {$error} A full trace was written to storage/logs/laravel.log.",
                $failure_status,
                $url,
                false,
                null,
                'connect',
                $error,
            );
        }

        $log = [
            'step' => 'http_response',
            'license_server_url' => $url,
            'reachable' => true,
            'status_code' => $response->status(),
            'body' => $this->redactLicenseKey(substr((string) $response->body(), 0, 500), $license_key),
        ];

        if ($response->successful()) {
            Log::info('White label license: license server responded', $log);
        } else {
            Log::warning('White label license: license server responded with an error', $log);
        }

        return $response;
    }

    private function hostedLicenseUrl(): string
    {
        return rtrim((string) config('ninja.hosted_ninja_url'), '/');
    }

    private function httpErrorMessage(string $url, int $status_code, string $detail): string
    {
        return "The license server at {$url} responded with HTTP {$status_code}. {$detail} A full trace was written to storage/logs/laravel.log.";
    }

    private function emptyResponseMessage(string $url, int $status_code): string
    {
        return "The license server at {$url} was reachable (HTTP {$status_code}) but returned an empty or unreadable response. A full trace was written to storage/logs/laravel.log.";
    }

    private function redactLicenseKey(string $value, string $license_key): string
    {
        $value = (string) preg_replace('/([?&](?:license_key|license)=)[^&\s]+/i', '$1[redacted]', $value);

        foreach (array_filter([$license_key, config('ninja.license_key')]) as $key) {
            if (is_string($key) && $key !== '') {
                $value = str_replace($key, '[redacted]', $value);
            }
        }

        return $value;
    }

    /**
     * @return array{license_server_url: string, reachable: bool|null, status_code: int|null, step: string, error?: string}
     */
    private function licenseContext(string $url, ?bool $reachable, ?int $status_code, string $step, ?string $error = null): array
    {
        $context = [
            'license_server_url' => $url,
            'reachable' => $reachable,
            'status_code' => $status_code,
            'step' => $step,
        ];

        if ($error) {
            $context['error'] = $error;
        }

        return $context;
    }

    private function licenseSuccess(string $message, string $url, int $status_code, string $step): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => new stdClass(),
            'context' => $this->licenseContext($url, true, $status_code, $step),
        ], 200);
    }

    private function licenseFailure(string $message, int $status, string $url, ?bool $reachable, ?int $status_code, string $step, ?string $error = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => new stdClass(),
            'context' => $this->licenseContext($url, $reachable, $status_code, $step, $error),
        ], $status);
    }
}
