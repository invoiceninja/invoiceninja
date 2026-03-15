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

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mollie\Api\Resources\Payment as ResourcesPayment;

class BankTransfer extends MolliePaymentMethod implements MethodInterface, LivewireMethodInterface
{
    protected const MOLLIE_PAYMENT_METHOD = 'banktransfer';

    protected const GATEWAY_TYPE_ID = GatewayType::BANK_TRANSFER;

    protected const PAYMENT_TYPE_ID = PaymentType::MOLLIE_BANK_TRANSFER;

    protected const AUTHORIZE_VIEW_TEMPLATE = 'gateways.mollie.bank_transfer.authorize';
}
