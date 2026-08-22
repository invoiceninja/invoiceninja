<?php

/**
 * Child process for GatewayFeeParallelConfirmTest.
 *
 * Boots the application and confirms one gateway fee. Started several at a time so the
 * confirmations genuinely contend for the invoice row.
 *
 * usage: php parallel_confirm.php <payment_hash> <company_gateway_id>
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hash = $argv[1] ?? null;
$company_gateway_id = (int) ($argv[2] ?? 0);

if (! $hash) {
    fwrite(STDERR, "no payment hash supplied\n");
    exit(1);
}

$payment_hash = App\Models\PaymentHash::query()->where('hash', $hash)->first();

if (! $payment_hash) {
    fwrite(STDERR, "payment hash {$hash} not found\n");
    exit(1);
}

$company_gateway = App\Models\CompanyGateway::query()->find($company_gateway_id);

try {
    (new App\Services\Invoice\ConfirmGatewayFee(
        $payment_hash,
        $company_gateway,
        ['gateway_type_id' => App\Models\GatewayType::CREDIT_CARD]
    ))->run();
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

exit(0);
