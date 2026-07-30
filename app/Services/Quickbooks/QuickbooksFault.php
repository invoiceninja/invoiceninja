<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks;

class QuickbooksFault
{
    /**
     * @param array<int, array{code: string, element: string, message: string, detail: string}> $errors
     */
    public function __construct(
        public readonly ?int $http_status,
        public readonly string $fault_type,
        public readonly array $errors,
        public readonly string $fallback_message,
    ) {}

    public function humanMessage(?string $operation = null): string
    {
        $context = $operation ? " while {$operation}" : '';

        if ($this->errors === []) {
            return mb_substr("QuickBooks request failed{$context}: {$this->fallback_message}", 0, 1000);
        }

        $messages = array_map(function (array $error) use ($context): string {
            $element = $error['element'] !== '' ? " {$error['element']}" : '';
            $code = $error['code'] !== '' ? " (error {$error['code']})" : '';
            $message = $error['message'] !== '' ? $error['message'] : 'The request was rejected.';
            $detail = $error['detail'] !== '' ? " {$error['detail']}" : '';

            return "QuickBooks rejected{$element}{$context}{$code}: {$message}{$detail}";
        }, $this->errors);

        return mb_substr(implode(' ', $messages), 0, 1000);
    }

    public function statusMessage(?string $operation = null): string
    {
        $error = $this->errors[0] ?? null;
        $code = $error['code'] ?? '';
        $element = $error['element'] ?? '';

        $message = match (true) {
            $this->http_status === 429 => 'QuickBooks is temporarily rate limiting requests. Please retry shortly.',
            $this->http_status === 401 || in_array($this->fault_type, ['AuthenticationFault', 'AuthorizationFault'], true)
                => 'QuickBooks authorization failed. Reconnect QuickBooks and retry.',
            $this->http_status !== null && $this->http_status >= 500,
            $this->fault_type === 'SystemFault'
                => 'QuickBooks is temporarily unavailable. Please retry shortly.',
            $code === '2040' && $element === 'DisplayName'
                => $this->displayNameStatusMessage($operation),
            $code === '6240'
                => 'This name already exists in QuickBooks (QB 6240). Use a unique name or link the existing record.',
            $code === '6140'
                => 'This document number already exists in QuickBooks (QB 6140). Change the number or link the existing transaction.',
            $code === '5010'
                => 'The QuickBooks record changed before this update completed (QB 5010). Refresh it and retry.',
            in_array($code, ['610', '2500', '6250'], true)
                => "A referenced QuickBooks record is missing or inactive (QB {$code}). Reactivate or relink it, then retry.",
            in_array($code, ['6200', '6210'], true)
                => "QuickBooks has closed this accounting period (QB {$code}). Reopen the period or make the change in QuickBooks.",
            $code === '6190'
                => 'The QuickBooks company is restricted by its subscription or billing status (QB 6190). Resolve it in QuickBooks.',
            default => $this->fallbackStatusMessage($error),
        };

        return mb_substr($message, 0, 255);
    }

    private function displayNameStatusMessage(?string $operation): string
    {
        $subject = $operation && str_contains($operation, 'customer')
            ? 'Customer DisplayName'
            : 'DisplayName';

        return "{$subject} contains characters QuickBooks does not support (QB 2040). Edit the name and retry.";
    }

    /**
     * @param array{code: string, element: string, message: string, detail: string}|null $error
     */
    private function fallbackStatusMessage(?array $error): string
    {
        if ($error === null) {
            return "QuickBooks request failed: {$this->fallback_message}";
        }

        $field = $error['element'] !== '' ? " {$error['element']}" : '';
        $code = $error['code'] !== '' ? " (QB {$error['code']})" : '';
        $message = $error['message'] !== '' ? $error['message'] : 'The request was rejected.';

        return "QuickBooks rejected{$field}{$code}: {$message}";
    }
}
