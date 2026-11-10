<?php

// Evidence checks for the SDK scenarios against the real fixture database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'ninja_mcp_test') {
    throw new RuntimeException('Not the fixture database.');
}
$fixture = json_decode(file_get_contents(base_path('.local/fixture.json')), true, flags: JSON_THROW_ON_ERROR);
$company = $fixture['companies']['alpha'];
$checks = [];
$checks['client_update'] = \App\Models\Client::where('company_id', $company)->where('name', 'MCP E2E Updated')->exists();
$task = \App\Models\Task::where('company_id', $company)->where('description', 'MCP E2E Tracked')->first();
$checks['time_tracking'] = $task !== null && $task->calcDuration() === 7200.0;
$checks['offline_refund'] = \App\Models\Payment::where('company_id', $company)->where('refunded', '>', 0)->exists();
$checks['activity_note'] = \App\Models\Activity::where('company_id', $company)->where('notes', 'MCP E2E Note')->exists();
$results = json_decode(file_get_contents(base_path('.local/e2e-results.json')), true, flags: JSON_THROW_ON_ERROR)['results'];
$scenarios = array_column($results, null, 'scenario');
$decoder = new class () {
    use \App\Utils\Traits\MakesHash;
};
$decode = fn (?string $id) => $id === null ? null : $decoder->decodePrimaryKey($id, true);
$refund = $scenarios['invoices.payment_and_refund'] ?? [];
$payment = \App\Models\Payment::where('company_id', $company)->find($decode($refund['payment'] ?? null));
$invoice = \App\Models\Invoice::where('company_id', $company)->find($decode($refund['invoice'] ?? null));
$checks['invoice_payment_refund'] = ($refund['result'] ?? null) === 'passed'
    && $payment !== null && $invoice !== null && (float) $payment->refunded === 25.0
    && \Illuminate\Support\Facades\DB::table('paymentables')->where('payment_id', $payment->id)
        ->where('paymentable_id', $invoice->id)->where('paymentable_type', $invoice->getMorphClass())
        ->where('amount', 100)->where('refunded', 25)->exists();
$conversion = $scenarios['quotes.convert_to_invoice'] ?? [];
$quote = \App\Models\Quote::where('company_id', $company)->find($decode($conversion['quote'] ?? null));
$convertedInvoice = \App\Models\Invoice::where('company_id', $company)->find($decode($conversion['invoice'] ?? null));
$checks['quote_conversion'] = ($conversion['result'] ?? null) === 'passed'
    && $quote !== null && $convertedInvoice !== null && $quote->invoice_id === $convertedInvoice->id
    && (float) $convertedInvoice->amount === 120.0 && $quote->client_id === $convertedInvoice->client_id;
file_put_contents(base_path('.local/db-effects.json'), json_encode($checks, JSON_PRETTY_PRINT));
echo json_encode($checks, JSON_PRETTY_PRINT).PHP_EOL;
if (in_array(false, $checks, true)) {
    exit(1);
}
