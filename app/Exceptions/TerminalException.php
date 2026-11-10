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

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An in person card payment state the mobile apps branch on, rendered with an error_code.
 */
class TerminalException extends Exception
{
    private const MESSAGES = [
        'disabled' => 'texts.terminal_disabled',
        'plan_required' => 'texts.terminal_plan_required',
        'not_enabled' => 'texts.terminal_not_enabled',
        'unsupported_currency' => 'texts.terminal_unsupported_currency',
        'collection_open' => 'texts.terminal_collection_open',
        'collection_closed' => 'texts.terminal_collection_closed',
        'already_paid' => 'texts.terminal_already_paid',
        'balance_changed' => 'texts.terminal_balance_changed',
        'amount_mismatch' => 'texts.terminal_amount_mismatch',
        'fee_not_allowed' => 'texts.terminal_fee_not_allowed',
        'send_invoice_first' => 'texts.terminal_send_invoice_first',
        'not_collected' => 'texts.terminal_not_collected',
        'location_address_required' => 'texts.terminal_location_address_required',
        'location_required' => 'texts.terminal_location_required',
        'receipt_limit' => 'texts.terminal_receipt_limit',
    ];

    public function __construct(public string $error_code, int $status = 409, public array $data = [], array $replace = [])
    {
        parent::__construct(ctrans(self::MESSAGES[$error_code] ?? 'texts.error_title', $replace), $status);
    }

    public function report(): void
    {
        //
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function render($request): JsonResponse
    {
        return response()->json(array_merge([
            'message' => $this->getMessage(),
            'error_code' => $this->error_code,
        ], $this->data), $this->getCode());
    }
}
