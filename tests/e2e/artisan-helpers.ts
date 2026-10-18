import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { decodePrimaryKey } from './hash-helpers';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

/**
 * Runs PHP inside the application the browser is talking to.
 *
 * Some invariants are only visible in the database - the company ledger, the payment
 * hash behind a fee line - and some scenarios have no HTTP entry point, such as a
 * gateway redelivering a webhook that has already been processed.
 */
export function runArtisan(phpCode: string): string {
    return execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' + phpCode,
        ],
        {
            cwd: projectRoot,
            encoding: 'utf8',
            env: process.env,
        },
    ).trim();
}

export interface LedgerRow {
    adjustment: number;
    notes: string;
}

/** Company ledger rows written against one invoice, oldest first. */
export function invoiceLedgerRows(invoiceHashedId: string): LedgerRow[] {
    const raw = runArtisan(
        `$invoice = \\App\\Models\\Invoice::on("db-ninja-01")->withTrashed()->findOrFail(${JSON.stringify(decodePrimaryKey(invoiceHashedId))});` +
            'echo $invoice->company_ledger()->orderBy("id")->get(["adjustment", "notes"])->toJson();',
    );

    return JSON.parse(raw) as LedgerRow[];
}

/** Ledger rows written by confirming or reversing a gateway fee. */
export function gatewayFeeLedgerRows(invoiceHashedId: string): LedgerRow[] {
    return invoiceLedgerRows(invoiceHashedId).filter((row) =>
        /gateway fee/i.test(row.notes ?? ''),
    );
}

/**
 * Replays a gateway fee confirmation for an attempt that has already been confirmed -
 * what a redelivered webhook does.
 */
export function reconfirmGatewayFee(
    paymentHash: string,
    companyGatewayHashedId: string,
    gatewayTypeId: number,
): void {
    runArtisan(
        `$hash = \\App\\Models\\PaymentHash::on("db-ninja-01")->where("hash", ${JSON.stringify(paymentHash)})->firstOrFail();` +
            `$gateway = \\App\\Models\\CompanyGateway::on("db-ninja-01")->findOrFail(${JSON.stringify(decodePrimaryKey(companyGatewayHashedId))});` +
            `(new \\App\\Services\\Invoice\\ConfirmGatewayFee($hash, $gateway, ["gateway_type_id" => ${gatewayTypeId}]))->run();` +
            'echo "ok";',
    );
}

/**
 * Registers the Stripe webhook endpoint for one company gateway.
 *
 * Async payment outcomes only reach the application by webhook, so a gateway with no
 * registered endpoint cannot report a debit as settled or failed.
 *
 * @see app/PaymentDrivers/Stripe/Jobs/StripeWebhook.php
 */
export function registerStripeWebhook(companyGatewayHashedId: string): string {
    return runArtisan(
        `$gateway = \\App\\Models\\CompanyGateway::on("db-ninja-01")->withTrashed()->findOrFail(${JSON.stringify(decodePrimaryKey(companyGatewayHashedId))});` +
            '(new \\App\\PaymentDrivers\\Stripe\\Jobs\\StripeWebhook($gateway->company->company_key, $gateway->id))->handle();' +
            'echo $gateway->webhookUrl();',
    );
}

export interface StoredAchToken {
    clientHashedId: string;
    companyGatewayHashedId: string;
    paymentMethodId: string;
    customerId: string;
    last4: string;
}

/**
 * Stores a Stripe bank account as a client payment method.
 *
 * The portal has no route that stores a specific PaymentMethod, and the bank accounts
 * offered by the Financial Connections test institution all succeed - so a debit that
 * fails has to be set up from Stripe's documented test payment methods.
 *
 * @see https://docs.stripe.com/payments/ach-direct-debit/accept-a-payment#test-account-numbers
 */
export function storeAchToken(token: StoredAchToken): string {
    return runArtisan(
        `$client = \\App\\Models\\Client::on("db-ninja-01")->findOrFail(${JSON.stringify(decodePrimaryKey(token.clientHashedId))});` +
            `$gateway = \\App\\Models\\CompanyGateway::on("db-ninja-01")->withTrashed()->findOrFail(${JSON.stringify(decodePrimaryKey(token.companyGatewayHashedId))});` +
            '$cgt = new \\App\\Models\\ClientGatewayToken();' +
            '$cgt->company_id = $client->company_id;' +
            '$cgt->client_id = $client->id;' +
            '$cgt->company_gateway_id = $gateway->id;' +
            '$cgt->gateway_type_id = \\App\\Models\\GatewayType::BANK_TRANSFER;' +
            `$cgt->token = ${JSON.stringify(token.paymentMethodId)};` +
            `$cgt->gateway_customer_reference = ${JSON.stringify(token.customerId)};` +
            '$cgt->is_default = true;' +
            `$cgt->meta = (object) ["state" => "authorized", "type" => \\App\\Models\\GatewayType::BANK_TRANSFER, "brand" => "TEST BANK", "last4" => ${JSON.stringify(token.last4)}];` +
            '$cgt->save();' +
            'echo $cgt->id;',
    );
}
