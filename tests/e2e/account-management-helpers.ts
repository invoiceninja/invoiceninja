import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { type ApiContext } from './api-helpers';
import { SMALL_ACCOUNT_EMAIL } from './accounts';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

const ACCOUNT_MANAGEMENT_PREFIX = '/api/client/account_management';

export interface UpgradeQuoteRequest {
    plan: 'pro' | 'enterprise';
    term: 'month' | 'year';
    users: number;
    docuninja_users?: number;
}

export interface UpgradeQuoteResponse {
    description?: string;
    ninja_price?: string;
    docuninja_price?: string;
    price?: string;
    pro_rata?: string;
    pro_rata_raw?: number;
    requires_payment?: boolean;
    credit?: string;
    credit_raw?: number;
    hash?: string;
    message?: string;
    errors?: Record<string, string[]>;
}

export interface AccountPlanState {
    plan: string | null;
    plan_term: string | null;
    plan_paid: string | null;
    plan_started: string | null;
    plan_expires: string | null;
    num_users: number;
    docuninja_num_users: number;
    is_trial: boolean;
    trial_started: string | null;
    key: string;
    is_paid: boolean;
    is_ninja: boolean;
    is_hosted: boolean;
}

export interface TrialAccountSetupOptions {
    docuninja_users?: number;
}

export interface AccountUserRecord {
    id: string;
    name: string;
    email: string;
    status: string;
}

export interface AccountInvoiceRecord {
    id: string;
    amount: string;
    balance: string;
    number: string;
    payable: boolean;
}

export interface PaidAccountSetupOptions {
    plan?: 'pro' | 'enterprise';
    term?: 'month' | 'year';
    users?: number;
    docuninja_users?: number;
    /** Days after plan_paid within the current billing period (default 14). */
    days_into_period?: number;
    /** Backdate plan_started/plan_paid to simulate an account outside the money-back window. */
    plan_started_days_ago?: number;
}

export interface PaidAccountSetupResult extends AccountPlanState {
    client_id: number | null;
    recurring_invoice_id: number | null;
}

export interface BillingRecurringState {
    recurring_invoice_id: number | null;
    product_keys: string[];
    docuninja_quantity: number;
    line_items_total: number;
    amount: number;
    plan_price: number | null;
    docuninja_price: number | null;
}

export interface PayableInvoiceSetupResult extends PaidAccountSetupResult {
    invoice_id: number;
    invoice_hashed_id: string;
    invoice_balance: number;
}

function runArtisanExecute(phpCode: string): string {
    const output = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', phpCode],
        {
            cwd: projectRoot,
            encoding: 'utf8',
            env: process.env,
        },
    );

    return output.trim();
}

function parseJsonLine<T>(output: string, label: string): T {
    const line = output
        .split('\n')
        .map((entry) => entry.trim())
        .filter(Boolean)
        .at(-1);

    if (!line) {
        throw new Error(`${label}: artisan tinker returned no output.`);
    }

    try {
        return JSON.parse(line) as T;
    } catch {
        throw new Error(`${label}: could not parse JSON from tinker output: ${line.slice(0, 300)}`);
    }
}

export function accountManagementOwnerEmail(): string {
    return (
        process.env.PLAYWRIGHT_ACCOUNT_MANAGEMENT_EMAIL?.trim() ??
        SMALL_ACCOUNT_EMAIL
    );
}

export function upgradeQuoteErrorText(body: UpgradeQuoteResponse): string {
    const validationErrors = Object.values(body.errors ?? {})
        .flat()
        .join(' ');

    return [body.message ?? '', validationErrors].filter(Boolean).join(' ');
}

export async function isAccountManagementAvailable(
    api: ApiContext,
): Promise<boolean> {
    const response = await api.request.get(
        `${ACCOUNT_MANAGEMENT_PREFIX}/plans`,
    );

    if (!response.ok()) {
        return false;
    }

    const body = await response.json();

    return Boolean(body.plans && body.products);
}

export async function accountManagementSkipReason(
    api: ApiContext,
): Promise<string | null> {
    if (!(await isAccountManagementAvailable(api))) {
        return 'invoiceninja/admin-api is not installed or account management routes are unavailable';
    }

    const probe = await getUpgradeDescription(api, {
        plan: 'pro',
        term: 'month',
        users: 1,
    });

    if (probe.status === 401 || probe.status === 403) {
        return `Account management upgrade routes are not accessible to the test owner (${probe.status})`;
    }

    if (probe.status === 404) {
        return 'Account management upgrade routes are unavailable';
    }

    if (probe.status >= 500) {
        return `Account management upgrade routes failed (${probe.status})`;
    }

    return null;
}

export function stripeConfigured(): boolean {
    if (process.env.STRIPE_KEYS?.trim() || process.env.NINJA_STRIPE_KEY?.trim()) {
        return true;
    }

    return Boolean(parseStripeSecretKey());
}

export function readBillingRecurringState(email: string): BillingRecurringState {
    return parseJsonLine(
        runArtisanExecute(
            `$user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->first();` +
                'if (!$user) { echo json_encode(["error" => "user not found"]); return; }' +
                '\\App\\Libraries\\MultiDB::findAndSetDbByAccountKey($user->account->key);' +
                '$account = $user->account;' +
                '$recurringId = (int) ($account->billing_context?->recurring_invoice_id ?? 0);' +
                '$recurring = $recurringId > 0' +
                ' ? \\App\\Models\\RecurringInvoice::on("db-ninja-01")->find($recurringId)' +
                ' : null;' +
                '$productKeys = [];' +
                '$docuninjaQuantity = 0;' +
                '$lineItemsTotal = 0.0;' +
                'foreach (($recurring->line_items ?? []) as $item) {' +
                '$productKey = (string) ($item->product_key ?? "");' +
                '$productKeys[] = $productKey;' +
                '$quantity = (float) ($item->quantity ?? 0);' +
                '$unitCost = (float) (($item->product_cost ?? 0) > 0 ? $item->product_cost : ($item->cost ?? 0));' +
                '$lineItemsTotal += round($quantity * $unitCost, 2);' +
                'if (stripos($productKey, "docuninja") !== false) {' +
                '$docuninjaQuantity += (int) $quantity;' +
                '}' +
                '}' +
                'echo json_encode([' +
                "'recurring_invoice_id' => \$recurring ? (int) \$recurring->id : null," +
                "'product_keys' => \$productKeys," +
                "'docuninja_quantity' => \$docuninjaQuantity," +
                "'line_items_total' => round(\$lineItemsTotal, 2)," +
                "'amount' => \$recurring ? round((float) \$recurring->amount, 2) : 0.0," +
                "'plan_price' => isset($account->billing_context?->pricing['plan_price']) ? (float) $account->billing_context->pricing['plan_price'] : null," +
                "'docuninja_price' => isset($account->billing_context?->pricing['docuninja_price']) ? (float) $account->billing_context->pricing['docuninja_price'] : null," +
                ']);',
        ),
        'billing recurring state',
    );
}

export function readAccountPlanState(email: string): AccountPlanState {
    return parseJsonLine(
        runArtisanExecute(
            `$user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->first();` +
                'if (!$user) { echo json_encode(["error" => "user not found"]); return; }' +
                '\\App\\Libraries\\MultiDB::findAndSetDbByAccountKey($user->account->key);' +
                '$account = $user->account;' +
                'echo json_encode([' +
                "'plan' => \$account->plan," +
                "'plan_term' => \$account->plan_term," +
                "'plan_paid' => \$account->plan_paid," +
                "'plan_started' => \$account->plan_started," +
                "'plan_expires' => \$account->plan_expires," +
                "'num_users' => (int) \$account->num_users," +
                "'docuninja_num_users' => (int) \$account->docuninja_num_users," +
                "'is_trial' => (bool) \$account->is_trial," +
                "'trial_started' => \$account->trial_started," +
                "'key' => (string) \$account->key," +
                "'is_paid' => \$account->isPaid()," +
                "'is_ninja' => \\App\\Utils\\Ninja::isNinja()," +
                "'is_hosted' => \\App\\Utils\\Ninja::isHosted()," +
                ']);',
        ),
        'account plan state',
    );
}

export function resetAccountPlanState(email: string): AccountPlanState {
    return parseJsonLine(
        runArtisanExecute(
            `$user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->firstOrFail();` +
                '\\App\\Libraries\\MultiDB::findAndSetDbByAccountKey($user->account->key);' +
                '$account = $user->account;' +
                '\\Illuminate\\Support\\Facades\\Cache::forget($account->key);' +
                '$account->plan = null;' +
                '$account->plan_term = null;' +
                '$account->plan_paid = null;' +
                '$account->plan_expires = null;' +
                '$account->plan_started = null;' +
                '$account->plan_price = 0;' +
                '$account->num_users = 1;' +
                '$account->docuninja_num_users = 0;' +
                '$account->is_trial = false;' +
                '$account->trial_plan = null;' +
                '$account->trial_started = null;' +
                '$account->payment_id = null;' +
                '$account->billing_context = null;' +
                '$account->save();' +
                'echo json_encode([' +
                "'plan' => \$account->plan," +
                "'plan_term' => \$account->plan_term," +
                "'plan_paid' => \$account->plan_paid," +
                "'plan_started' => \$account->plan_started," +
                "'plan_expires' => \$account->plan_expires," +
                "'num_users' => (int) \$account->num_users," +
                "'docuninja_num_users' => (int) \$account->docuninja_num_users," +
                "'is_trial' => (bool) \$account->is_trial," +
                "'trial_started' => \$account->trial_started," +
                "'key' => (string) \$account->key," +
                "'is_paid' => \$account->isPaid()," +
                "'is_ninja' => \\App\\Utils\\Ninja::isNinja()," +
                "'is_hosted' => \\App\\Utils\\Ninja::isHosted()," +
                ']);',
        ),
        'reset account plan state',
    );
}

export function preparePaidAccountForQuotes(
    email: string,
    options: PaidAccountSetupOptions = {},
): PaidAccountSetupResult {
    const payload = JSON.stringify({
        plan: options.plan ?? 'pro',
        term: options.term ?? 'month',
        users: options.users ?? 1,
        docuninja_users: options.docuninja_users ?? 0,
        days_into_period: options.days_into_period ?? 14,
        plan_started_days_ago: options.plan_started_days_ago ?? null,
    });

    return parseJsonLine(
        runArtisanExecute(
            `$options = json_decode(${JSON.stringify(payload)}, true);` +
                `$user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->firstOrFail();` +
                '\\App\\Libraries\\MultiDB::findAndSetDbByAccountKey($user->account->key);' +
                '$account = $user->account;' +
                '\\Illuminate\\Support\\Facades\\Cache::forget($account->key);' +
                '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' +
                '$ninjaCompanyId = (int) config("ninja.ninja_default_company_id");' +
                'if ($ninjaCompanyId <= 0) { echo json_encode(["error" => "missing ninja company id"]); return; }' +
                '$ninjaCompany = \\App\\Models\\Company::on("db-ninja-01")->find($ninjaCompanyId);' +
                'if (!$ninjaCompany) { echo json_encode(["error" => "billing company not found on db-ninja-01"]); return; }' +
                '$ninjaOwnerId = $ninjaCompany->owner()->id;' +
                '$client = \\App\\Models\\Client::on("db-ninja-01")->firstOrCreate(' +
                '["company_id" => $ninjaCompanyId, "custom_value2" => $account->key],' +
                '["user_id" => $ninjaOwnerId, "name" => trim($user->first_name . " " . $user->last_name) ?: $user->email]' +
                ');' +
                '$plan = $options["plan"];' +
                '$term = $options["term"];' +
                '$users = (int) $options["users"];' +
                '$docuninjaUsers = (int) $options["docuninja_users"];' +
                '$planProduct = match (true) {' +
                '$plan === "pro" && $term === "year" => "pro_plan_annual",' +
                '$plan === "pro" => "pro_plan",' +
                '$plan === "enterprise" && $term === "year" => "enterprise_plan_annual",' +
                '$plan === "enterprise" && $users <= 2 => "enterprise_plan",' +
                '$plan === "enterprise" && $users <= 5 => "enterprise_plan_5",' +
                '$plan === "enterprise" && $users <= 10 => "enterprise_plan_10",' +
                '$plan === "enterprise" && $users <= 20 => "enterprise_plan_20",' +
                '$plan === "enterprise" && $users <= 30 => "enterprise_plan_30",' +
                'default => "enterprise_plan_50",' +
                '};' +
                '$planPrice = (float) (config("admin-api.plans")[$planProduct] ?? 0);' +
                '$docuninjaPrice = $docuninjaUsers > 0 ? (float) (config("admin-api.plans")[$term === "year" ? "docuninja_user_annual" : "docuninja_user"] ?? 0) : 0.0;' +
                'if (!empty($options["plan_started_days_ago"])) {' +
                '$planPaid = now()->subDays((int) $options["plan_started_days_ago"])->startOfDay();' +
                '$planExpires = $term === "year" ? $planPaid->copy()->addYear() : $planPaid->copy()->addMonth();' +
                '} else {' +
                '$daysIntoPeriod = max(0, (int) ($options["days_into_period"] ?? 14));' +
                '$planPaid = now()->startOfDay()->subDays($daysIntoPeriod);' +
                '$planExpires = $term === "year" ? $planPaid->copy()->addYear() : $planPaid->copy()->addMonth();' +
                '}' +
                '$lineItems = [(object) ["product_key" => $planProduct, "notes" => "Plan", "cost" => $planPrice, "product_cost" => $planPrice, "quantity" => 1, "type_id" => "1"]];' +
                'if ($docuninjaUsers > 0) {' +
                '$lineItems[] = (object) ["product_key" => ($term === "year" ? "docuninja_user_annual" : "docuninja_user"), "notes" => "DocuNinja", "cost" => $docuninjaPrice, "product_cost" => $docuninjaPrice, "quantity" => $docuninjaUsers, "type_id" => "1"];' +
                '}' +
                '$recurring = (new \\App\\Models\\RecurringInvoice())->forceFill([' +
                '"company_id" => $ninjaCompanyId,' +
                '"user_id" => $ninjaOwnerId,' +
                '"client_id" => $client->id,' +
                '"frequency_id" => $term === "year" ? \\App\\Models\\RecurringInvoice::FREQUENCY_ANNUALLY : \\App\\Models\\RecurringInvoice::FREQUENCY_MONTHLY,' +
                '"line_items" => $lineItems,' +
                '"amount" => $planPrice + ($docuninjaPrice * $docuninjaUsers),' +
                '"balance" => 0,' +
                '"status_id" => \\App\\Models\\RecurringInvoice::STATUS_ACTIVE,' +
                '"is_deleted" => false,' +
                '"subscription_id" => (int) (config("admin-api.products")[$planProduct]["subscription_id"] ?? 0),' +
                ']);' +
                '$recurring->setConnection("db-ninja-01");' +
                '$recurring->save();' +
                '$account->plan = $plan;' +
                '$account->plan_term = $term;' +
                '$account->plan_paid = $planPaid->format("Y-m-d");' +
                '$account->plan_expires = $planExpires->format("Y-m-d");' +
                '$account->plan_started = $planPaid->format("Y-m-d");' +
                '$account->num_users = $users;' +
                '$account->docuninja_num_users = $docuninjaUsers;' +
                '$account->is_trial = false;' +
                '$account->billing_context = new \\App\\DataMapper\\Billing\\BillingContext(' +
                'client_id: (int) $client->id,' +
                'recurring_invoice_id: (int) $recurring->id,' +
                'pricing: ["plan_price" => $planPrice, "docuninja_price" => $docuninjaPrice],' +
                ');' +
                '$account->save();' +
                'echo json_encode([' +
                "'plan' => \$account->plan," +
                "'plan_term' => \$account->plan_term," +
                "'plan_paid' => \$account->plan_paid," +
                "'plan_started' => \$account->plan_started," +
                "'plan_expires' => \$account->plan_expires," +
                "'num_users' => (int) \$account->num_users," +
                "'docuninja_num_users' => (int) \$account->docuninja_num_users," +
                "'is_trial' => (bool) \$account->is_trial," +
                "'trial_started' => \$account->trial_started," +
                "'key' => (string) \$account->key," +
                "'is_paid' => \$account->isPaid()," +
                "'is_ninja' => \\App\\Utils\\Ninja::isNinja()," +
                "'is_hosted' => \\App\\Utils\\Ninja::isHosted()," +
                "'client_id' => (int) \$client->id," +
                "'recurring_invoice_id' => (int) \$recurring->id," +
                ']);',
        ),
        'prepare paid account for quotes',
    );
}

export function prepareTrialAccount(
    email: string,
    options: TrialAccountSetupOptions = {},
): PaidAccountSetupResult {
    const payload = JSON.stringify({
        docuninja_users: options.docuninja_users ?? 0,
    });

    return parseJsonLine(
        runArtisanExecute(
            `$options = json_decode(${JSON.stringify(payload)}, true);` +
                `$user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->firstOrFail();` +
                '\\App\\Libraries\\MultiDB::findAndSetDbByAccountKey($user->account->key);' +
                '$account = $user->account;' +
                '\\Illuminate\\Support\\Facades\\Cache::forget($account->key);' +
                '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' +
                '$ninjaCompanyId = (int) config("ninja.ninja_default_company_id");' +
                'if ($ninjaCompanyId <= 0) { echo json_encode(["error" => "missing ninja company id"]); return; }' +
                '$ninjaCompany = \\App\\Models\\Company::on("db-ninja-01")->find($ninjaCompanyId);' +
                'if (!$ninjaCompany) { echo json_encode(["error" => "billing company not found on db-ninja-01"]); return; }' +
                '$ninjaOwnerId = $ninjaCompany->owner()->id;' +
                '$client = \\App\\Models\\Client::on("db-ninja-01")->firstOrCreate(' +
                '["company_id" => $ninjaCompanyId, "custom_value2" => $account->key],' +
                '["user_id" => $ninjaOwnerId, "name" => trim($user->first_name . " " . $user->last_name) ?: $user->email]' +
                ');' +
                '$docuninjaUsers = (int) $options["docuninja_users"];' +
                '$planPrice = (float) (config("admin-api.plans")["pro_plan"] ?? 14);' +
                '$docuninjaPrice = $docuninjaUsers > 0 ? (float) (config("admin-api.plans")["docuninja_user"] ?? 0) : 0.0;' +
                '$planStarted = now()->startOfDay();' +
                '$planExpires = $planStarted->copy()->addDays(14);' +
                '$lineItems = [(object) ["product_key" => "pro_plan", "notes" => "Plan", "cost" => $planPrice, "product_cost" => $planPrice, "quantity" => 1, "type_id" => "1"]];' +
                'if ($docuninjaUsers > 0) {' +
                '$lineItems[] = (object) ["product_key" => "docuninja_user", "notes" => "DocuNinja", "cost" => $docuninjaPrice, "product_cost" => $docuninjaPrice, "quantity" => $docuninjaUsers, "type_id" => "1"];' +
                '}' +
                '$recurring = (new \\App\\Models\\RecurringInvoice())->forceFill([' +
                '"company_id" => $ninjaCompanyId,' +
                '"user_id" => $ninjaOwnerId,' +
                '"client_id" => $client->id,' +
                '"frequency_id" => \\App\\Models\\RecurringInvoice::FREQUENCY_MONTHLY,' +
                '"line_items" => $lineItems,' +
                '"amount" => $planPrice + ($docuninjaPrice * $docuninjaUsers),' +
                '"balance" => 0,' +
                '"status_id" => \\App\\Models\\RecurringInvoice::STATUS_ACTIVE,' +
                '"is_deleted" => false,' +
                '"subscription_id" => (int) (config("admin-api.products")["pro_plan"]["subscription_id"] ?? 0),' +
                ']);' +
                '$recurring->setConnection("db-ninja-01");' +
                '$recurring->next_send_date = $planExpires->format("Y-m-d");' +
                '$recurring->next_send_date_client = $planExpires->format("Y-m-d");' +
                '$recurring->save();' +
                '$recurring->service()->start()->save();' +
                '$account->plan = "pro";' +
                '$account->plan_term = "month";' +
                '$account->plan_paid = null;' +
                '$account->plan_started = $planStarted->format("Y-m-d");' +
                '$account->plan_expires = $planExpires->format("Y-m-d");' +
                '$account->num_users = 1;' +
                '$account->docuninja_num_users = $docuninjaUsers;' +
                '$account->is_trial = true;' +
                '$account->trial_started = now();' +
                '$account->trial_plan = "pro";' +
                '$account->hosted_company_count = 10;' +
                '$account->billing_context = new \\App\\DataMapper\\Billing\\BillingContext(' +
                'client_id: (int) $client->id,' +
                'recurring_invoice_id: (int) $recurring->id,' +
                'pricing: ["plan_price" => $planPrice, "docuninja_price" => $docuninjaPrice],' +
                ');' +
                '$account->save();' +
                'echo json_encode([' +
                "'plan' => \$account->plan," +
                "'plan_term' => \$account->plan_term," +
                "'plan_paid' => \$account->plan_paid," +
                "'plan_started' => \$account->plan_started," +
                "'plan_expires' => \$account->plan_expires," +
                "'num_users' => (int) \$account->num_users," +
                "'docuninja_num_users' => (int) \$account->docuninja_num_users," +
                "'is_trial' => (bool) \$account->is_trial," +
                "'trial_started' => \$account->trial_started," +
                "'key' => (string) \$account->key," +
                "'is_paid' => \$account->isPaid()," +
                "'is_ninja' => \\App\\Utils\\Ninja::isNinja()," +
                "'is_hosted' => \\App\\Utils\\Ninja::isHosted()," +
                "'client_id' => (int) \$client->id," +
                "'recurring_invoice_id' => (int) \$recurring->id," +
                ']);',
        ),
        'prepare trial account',
    );
}

export function accountCanStartTrial(email: string): boolean {
    return parseJsonLine<{ can_trial: boolean }>(
        runArtisanExecute(
            `$user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->firstOrFail();` +
                '\\App\\Libraries\\MultiDB::findAndSetDbByAccountKey($user->account->key);' +
                '$account = $user->account;' +
                'echo json_encode(["can_trial" => (bool) $account->canTrial()]);',
        ),
        'account can start trial',
    ).can_trial;
}

export function preparePayableBillingInvoice(
    email: string,
    options: PaidAccountSetupOptions = {},
): PayableInvoiceSetupResult {
    const paid = preparePaidAccountForQuotes(email, options);

    return parseJsonLine(
        runArtisanExecute(
            `$user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->firstOrFail();` +
                '\\App\\Libraries\\MultiDB::findAndSetDbByAccountKey($user->account->key);' +
                '$account = $user->account;' +
                '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' +
                '$recurringId = (int) ($account->billing_context?->recurring_invoice_id ?? 0);' +
                '$recurring = \\App\\Models\\RecurringInvoice::on("db-ninja-01")->find($recurringId);' +
                'if (!$recurring) { echo json_encode(["error" => "missing recurring invoice"]); return; }' +
                '$subscriptionId = (int) ($recurring->subscription_id ?? 0);' +
                'if ($subscriptionId <= 0) { echo json_encode(["error" => "missing subscription on recurring invoice"]); return; }' +
                '$amount = round((float) $recurring->amount, 2);' +
                '$invoice = \\App\\Models\\Invoice::factory()->make([' +
                '"company_id" => $recurring->company_id,' +
                '"user_id" => $recurring->user_id,' +
                '"client_id" => $recurring->client_id,' +
                '"recurring_id" => $recurring->id,' +
                '"subscription_id" => $subscriptionId,' +
                '"status_id" => \\App\\Models\\Invoice::STATUS_SENT,' +
                '"amount" => $amount,' +
                '"balance" => $amount,' +
                '"line_items" => $recurring->line_items,' +
                '"date" => now()->format("Y-m-d"),' +
                '"due_date" => now()->addDays(30)->format("Y-m-d"),' +
                ']);' +
                '$invoice->setConnection("db-ninja-01");' +
                '$invoice->service()->fillDefaults()->save();' +
                'echo json_encode([' +
                "'invoice_id' => (int) \$invoice->id," +
                "'invoice_hashed_id' => (string) \$invoice->hashed_id," +
                "'invoice_balance' => (float) \$invoice->balance," +
                "'client_id' => (int) \$recurring->client_id," +
                "'recurring_invoice_id' => (int) \$recurring->id," +
                "'plan' => \$account->plan," +
                "'plan_term' => \$account->plan_term," +
                "'plan_paid' => \$account->plan_paid," +
                "'plan_started' => \$account->plan_started," +
                "'plan_expires' => \$account->plan_expires," +
                "'num_users' => (int) \$account->num_users," +
                "'docuninja_num_users' => (int) \$account->docuninja_num_users," +
                "'is_trial' => (bool) \$account->is_trial," +
                "'trial_started' => \$account->trial_started," +
                "'key' => (string) \$account->key," +
                "'is_paid' => \$account->isPaid()," +
                "'is_ninja' => \\App\\Utils\\Ninja::isNinja()," +
                "'is_hosted' => \\App\\Utils\\Ninja::isHosted()," +
                ']);',
        ),
        'prepare payable billing invoice',
    );
}

export function seedDocuNinjaBetaAllowlist(email: string): void {
    runArtisanExecute(
        `$email = ${JSON.stringify(email)};` +
            '$users = \\Illuminate\\Support\\Facades\\Cache::get("docuninja_beta") ?? [];' +
            'if (!in_array($email, $users, true)) { $users[] = $email; }' +
            '\\Illuminate\\Support\\Facades\\Cache::forever("docuninja_beta", $users);' +
            'echo json_encode(["seeded" => true]);',
    );
}

export function clearDocuNinjaBetaAllowlist(email: string): void {
    runArtisanExecute(
        `$email = ${JSON.stringify(email)};` +
            '$users = array_values(array_filter(' +
            '\\Illuminate\\Support\\Facades\\Cache::get("docuninja_beta") ?? [],' +
            'fn ($entry) => $entry !== $email' +
            '));' +
            '\\Illuminate\\Support\\Facades\\Cache::forever("docuninja_beta", $users);' +
            'echo json_encode(["cleared" => true]);',
    );
}

export function docuNinjaBetaCode(): string {
    return parseJsonLine<{ code: string }>(
        runArtisanExecute(
            'echo json_encode(["code" => config("admin-api.products.docuninja_beta_code")]);',
        ),
        'docuninja beta code',
    ).code;
}

export async function getAccountManagementPlans(
    api: ApiContext,
): Promise<{ plans: Record<string, number>; products: Record<string, unknown> }> {
    const response = await api.request.get(
        `${ACCOUNT_MANAGEMENT_PREFIX}/plans`,
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to load account management plans (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    return response.json();
}

export async function getUpgradeDescription(
    api: ApiContext,
    request: UpgradeQuoteRequest,
): Promise<{ status: number; body: UpgradeQuoteResponse }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/v2/description`,
        { data: request },
    );

    return {
        status: response.status(),
        body: (await response.json()) as UpgradeQuoteResponse,
    };
}

export async function createUpgradePaymentIntent(
    api: ApiContext,
    amount?: number,
): Promise<{
    status: number;
    requires_payment: boolean;
    client_secret: string | null;
    payment_intent_id: string | null;
    message?: string;
}> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/payment/intent`,
        {
            data: {
                amount: amount ?? 0,
                hash: 'playwright',
            },
        },
    );

    const body = (await response.json()) as {
        requires_payment?: boolean;
        client_secret?: string | null;
        message?: string;
    };

    return {
        status: response.status(),
        requires_payment: Boolean(body.requires_payment),
        client_secret: body.client_secret ?? null,
        payment_intent_id: extractPaymentIntentId(body.client_secret),
        message: body.message,
    };
}

export async function completeUpgradePayment(
    api: ApiContext,
    paymentIntentId?: string | null,
): Promise<{ status: number; message: string }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/v2/payment`,
        {
            data: paymentIntentId
                ? { payment_intent: paymentIntentId, hash: 'playwright' }
                : {},
        },
    );

    const body = (await response.json()) as { message?: string };

    return {
        status: response.status(),
        message: body.message ?? (await response.text()).slice(0, 300),
    };
}

export async function downgradeToFree(
    api: ApiContext,
): Promise<{ status: number; message: string }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/free`,
        { data: {} },
    );

    const body = (await response.json()) as { message?: string };

    return {
        status: response.status(),
        message: body.message ?? (await response.text()).slice(0, 300),
    };
}

export async function downgradeDocuNinjaSeats(
    api: ApiContext,
    numUsers: number,
): Promise<{ status: number; message: string }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/docuninja/downgrade`,
        { data: { num_users: numUsers } },
    );

    const body = (await response.json()) as { message?: string };

    return {
        status: response.status(),
        message: body.message ?? (await response.text()).slice(0, 300),
    };
}

export async function startTrial(
    api: ApiContext,
): Promise<{ status: number; message: string }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/start_trial`,
        { data: {} },
    );

    if (response.status() === 204) {
        return { status: response.status(), message: '' };
    }

    const body = (await response.json()) as { message?: string };

    return {
        status: response.status(),
        message: body.message ?? (await response.text()).slice(0, 300),
    };
}

export async function cancelTrial(
    api: ApiContext,
): Promise<{ status: number; message: string }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/cancel_trial`,
        { data: {} },
    );

    return {
        status: response.status(),
        message: response.status() === 204 ? '' : (await response.text()).slice(0, 300),
    };
}

export async function listAccountUsers(
    api: ApiContext,
): Promise<{ status: number; users: AccountUserRecord[] }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/v2/users`,
        { data: {} },
    );

    const body = (await response.json()) as { users?: AccountUserRecord[] };

    return {
        status: response.status(),
        users: body.users ?? [],
    };
}

export async function listAccountInvoices(
    api: ApiContext,
): Promise<{ status: number; invoices: AccountInvoiceRecord[] }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/invoices`,
        { data: {} },
    );

    const body = (await response.json()) as { invoices?: AccountInvoiceRecord[] };

    return {
        status: response.status(),
        invoices: body.invoices ?? [],
    };
}

export async function listPaymentMethods(
    api: ApiContext,
): Promise<{ status: number; methods: unknown[] }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/methods`,
        { data: {} },
    );

    const body = (await response.json()) as {
        data?: { data?: unknown[] };
    };

    return {
        status: response.status(),
        methods: body.data?.data ?? [],
    };
}

export async function createPaymentMethodSetupIntent(
    api: ApiContext,
): Promise<{
    status: number;
    client_secret: string | null;
    setup_intent_id: string | null;
    message?: string;
}> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/methods/intent`,
        { data: {} },
    );

    const body = (await response.json()) as {
        client_secret?: string | null;
        id?: string | null;
        message?: string;
    };

    return {
        status: response.status(),
        client_secret: body.client_secret ?? null,
        setup_intent_id: body.id ?? null,
        message: body.message,
    };
}

export async function createInvoicePaymentIntent(
    api: ApiContext,
    invoiceHashedId: string,
): Promise<{
    status: number;
    requires_payment: boolean;
    client_secret: string | null;
    message?: string;
}> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/invoices/payment/intent`,
        { data: { id: invoiceHashedId } },
    );

    const body = (await response.json()) as {
        requires_payment?: boolean;
        client_secret?: string | null;
        message?: string;
    };

    return {
        status: response.status(),
        requires_payment: Boolean(body.requires_payment),
        client_secret: body.client_secret ?? null,
        message: body.message,
    };
}

export async function completeInvoicePayment(
    api: ApiContext,
    invoiceHashedId: string,
    paymentIntentId: string,
): Promise<{ status: number; message: string }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/invoices/payment/response`,
        {
            data: {
                id: invoiceHashedId,
                payment_intent: paymentIntentId,
            },
        },
    );

    const body = (await response.json()) as { message?: string };

    return {
        status: response.status(),
        message: body.message ?? (await response.text()).slice(0, 300),
    };
}

export async function downloadAccountInvoice(
    api: ApiContext,
    invoiceHashedId: string,
): Promise<{ status: number; contentType: string; byteLength: number }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/invoices/download`,
        { data: { id: invoiceHashedId } },
    );

    const buffer = await response.body();

    return {
        status: response.status(),
        contentType: response.headers()['content-type'] ?? '',
        byteLength: buffer.byteLength,
    };
}

export async function requestDocuNinjaBetaUpgrade(
    api: ApiContext,
    code: string,
): Promise<{ status: number; message: string }> {
    const response = await api.request.post(
        `${ACCOUNT_MANAGEMENT_PREFIX}/v2/docuninja/beta`,
        { data: { code } },
    );

    const body = (await response.json()) as {
        message?: string;
        errors?: Record<string, string[]>;
    };

    return {
        status: response.status(),
        message: upgradeQuoteErrorText(body),
    };
}

export async function payBillingInvoice(
    api: ApiContext,
    invoiceHashedId: string,
): Promise<{ status: number; message: string }> {
    const intent = await createInvoicePaymentIntent(api, invoiceHashedId);

    if (intent.status !== 200) {
        throw new Error(
            `Invoice PaymentIntent failed (${intent.status}): ${intent.message ?? 'unknown error'}`,
        );
    }

    if (!intent.requires_payment || !intent.client_secret) {
        return completeInvoicePayment(api, invoiceHashedId, 'pi_not_required');
    }

    const paymentIntentId = await confirmStripePaymentIntent(
        intent.client_secret,
    );

    return completeInvoicePayment(api, invoiceHashedId, paymentIntentId);
}

export function extractPaymentIntentId(
    clientSecret: string | null | undefined,
): string | null {
    if (!clientSecret) {
        return null;
    }

    const [paymentIntentId] = clientSecret.split('_secret_');

    return paymentIntentId?.startsWith('pi_') ? paymentIntentId : null;
}

export async function confirmStripePaymentIntent(
    clientSecret: string,
): Promise<string> {
    const paymentIntentId = extractPaymentIntentId(clientSecret);

    if (!paymentIntentId) {
        throw new Error('Could not parse a PaymentIntent id from the client secret.');
    }

    const secretKey = parseStripeSecretKey();

    if (!secretKey) {
        throw new Error(
            'Stripe secret key unavailable. Set STRIPE_KEYS or configure the hosted company gateway with sk_test_.',
        );
    }

    const response = await fetch(
        `https://api.stripe.com/v1/payment_intents/${paymentIntentId}/confirm`,
        {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${secretKey}`,
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                payment_method: 'pm_card_visa',
            }),
        },
    );

    const body = (await response.json()) as {
        error?: { message?: string };
        status?: string;
        id?: string;
    };

    if (!response.ok) {
        throw new Error(
            `Stripe PaymentIntent confirm failed (${response.status}): ${body.error?.message ?? JSON.stringify(body).slice(0, 300)}`,
        );
    }

    if (body.status !== 'succeeded') {
        throw new Error(
            `Stripe PaymentIntent ${paymentIntentId} did not succeed (status=${body.status ?? 'unknown'}).`,
        );
    }

    return paymentIntentId;
}

export async function performUpgrade(
    api: ApiContext,
    request: UpgradeQuoteRequest,
): Promise<UpgradeQuoteResponse> {
    const description = await getUpgradeDescription(api, request);

    if (description.status !== 200) {
        throw new Error(
            `Upgrade description failed (${description.status}): ${description.body.message ?? JSON.stringify(description.body).slice(0, 300)}`,
        );
    }

    if (!description.body.requires_payment) {
        const payment = await completeUpgradePayment(api);

        if (payment.status !== 200) {
            throw new Error(
                `Zero-cost upgrade failed (${payment.status}): ${payment.message}`,
            );
        }

        return description.body;
    }

    const intent = await createUpgradePaymentIntent(
        api,
        description.body.pro_rata_raw,
    );

    if (intent.status !== 200 || !intent.client_secret) {
        throw new Error(
            `PaymentIntent creation failed (${intent.status}): ${intent.message ?? 'missing client_secret'}`,
        );
    }

    const paymentIntentId = await confirmStripePaymentIntent(
        intent.client_secret,
    );
    const payment = await completeUpgradePayment(api, paymentIntentId);

    if (payment.status !== 200) {
        throw new Error(
            `Upgrade payment failed (${payment.status}): ${payment.message}`,
        );
    }

    return description.body;
}

function stripeSecretFromEnvValue(raw: string): string | null {
    if (raw.startsWith('sk_')) {
        return raw;
    }

    try {
        const parsed = JSON.parse(raw) as Record<string, unknown>;

        for (const key of ['apiKey', 'secretKey', 'secret', 'api_key']) {
            const value = parsed[key];

            if (typeof value === 'string' && value.startsWith('sk_')) {
                return value;
            }
        }
    } catch {
        const match = raw.match(/sk_(?:test|live)_[A-Za-z0-9]+/);

        if (match) {
            return match[0];
        }
    }

    return null;
}

function parseStripeSecretKey(): string | null {
    const raw = process.env.STRIPE_KEYS?.trim();

    if (raw) {
        const secret = stripeSecretFromEnvValue(raw);

        if (secret) {
            return secret;
        }
    }

    const ninjaStripe = process.env.NINJA_STRIPE_KEY?.trim();

    if (ninjaStripe?.startsWith('sk_')) {
        return ninjaStripe;
    }

    try {
        const gatewaySecret = parseJsonLine<{ apiKey?: string }>(
            runArtisanExecute(
                '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' +
                    '$gateway = \\App\\Models\\CompanyGateway::on("db-ninja-01")->find(config("ninja.ninja_default_company_gateway_id"));' +
                    'echo json_encode(["apiKey" => $gateway?->getConfigField("apiKey")]);',
            ),
            'gateway stripe key',
        );

        return gatewaySecret.apiKey?.startsWith('sk_')
            ? gatewaySecret.apiKey
            : null;
    } catch {
        return null;
    }
}

export function isPlausibleBillingPeriod(
    days: number,
    term: 'month' | 'year',
): boolean {
    if (term === 'year') {
        return days >= 360 && days <= 366;
    }

    return days >= 28 && days <= 31;
}

export function proRataRatio(
    planPaid: string,
    planExpires: string,
    planTerm: 'month' | 'year',
    now = new Date(),
): number {
    const expires = startOfDay(new Date(`${planExpires}T00:00:00`));
    const today = startOfDay(now);

    if (expires.getTime() <= today.getTime()) {
        return 0;
    }

    const daysLeft =
        (expires.getTime() - today.getTime()) / (24 * 60 * 60 * 1000);

    let periodDays = planTerm === 'year' ? 365 : 30;
    const paid = startOfDay(new Date(`${planPaid}T00:00:00`));
    const candidate = Math.round(
        (expires.getTime() - paid.getTime()) / (24 * 60 * 60 * 1000),
    );

    if (isPlausibleBillingPeriod(candidate, planTerm)) {
        periodDays = candidate;
    }

    const ratio = daysLeft / Math.max(1, periodDays);

    return Math.max(0, Math.min(1, ratio));
}

export function roundMoney(value: number): number {
    return Math.round(value * 100) / 100;
}

export function expectedMidCycleCharge(
    deltaTotal: number,
    ratio: number,
): number {
    return roundMoney(deltaTotal * ratio);
}

export interface BillingEngineQuote {
    total: number;
    pro_rata: number;
    ninja_price: number;
    docuninja_price: number;
    credit: number;
    ratio: number;
}

export interface ProRataScenario {
    name: string;
    setup: PaidAccountSetupOptions;
    request: UpgradeQuoteRequest;
}

export function getBillingEngineQuote(
    email: string,
    request: UpgradeQuoteRequest,
): BillingEngineQuote {
    const payload = JSON.stringify({
        plan: request.plan,
        term: request.term,
        users: request.users,
        docuninja_users: request.docuninja_users ?? 0,
    });

    return parseJsonLine(
        runArtisanExecute(
            `$request = json_decode(${JSON.stringify(payload)}, true);` +
                `$user = \\App\\Models\\User::where('email', ${JSON.stringify(email)})->firstOrFail();` +
                '\\App\\Libraries\\MultiDB::findAndSetDbByAccountKey($user->account->key);' +
                '$account = $user->account->fresh();' +
                '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' +
                '$clientId = (int) ($account->billing_context?->client_id ?? 0);' +
                '$client = $clientId > 0 ? \\App\\Models\\Client::on("db-ninja-01")->find($clientId) : null;' +
                'if (!$client) { echo json_encode(["error" => "billing client not found for account"]); return; }' +
                '$product = (new \\InvoiceNinja\\AdminApi\\Services\\AccountManagement\\V2\\PlanProductResolver())->forRequest($request["plan"], (int) $request["users"], $request["term"]);' +
                '$upgrade = new \\InvoiceNinja\\AdminApi\\Services\\Accounting\\Upgrade($account, $client);' +
                '[$total, $proRata, $ninja, $docuninja, $credit, $ratio] = $upgrade->getUpgradePrice($product, (int) $request["users"], (int) $request["docuninja_users"], $request["term"]);' +
                'echo json_encode([' +
                "'total' => (float) $total," +
                "'pro_rata' => (float) $proRata," +
                "'ninja_price' => (float) $ninja," +
                "'docuninja_price' => (float) $docuninja," +
                "'credit' => (float) $credit," +
                "'ratio' => (float) $ratio," +
                ']);',
        ),
        'billing engine quote',
    );
}

export function assertUpgradeQuoteMatchesEngine(
    quote: UpgradeQuoteResponse,
    engine: BillingEngineQuote,
): void {
    if (quote.pro_rata_raw !== engine.pro_rata) {
        throw new Error(
            `pro_rata mismatch: api=${quote.pro_rata_raw} engine=${engine.pro_rata} ratio=${engine.ratio}`,
        );
    }

    const apiCredit = quote.credit_raw ?? 0;

    if (apiCredit !== engine.credit) {
        throw new Error(
            `credit mismatch: api=${apiCredit} engine=${engine.credit} ratio=${engine.ratio}`,
        );
    }

    const apiRequiresPayment = quote.requires_payment ?? false;
    const engineRequiresPayment = engine.pro_rata > 0;

    if (apiRequiresPayment !== engineRequiresPayment) {
        throw new Error(
            `requires_payment mismatch: api=${apiRequiresPayment} engine=${engineRequiresPayment}`,
        );
    }
}

export async function fetchStripePaymentIntentAmountCents(
    paymentIntentId: string,
): Promise<number> {
    const secretKey = parseStripeSecretKey();

    if (!secretKey) {
        throw new Error('Stripe secret key unavailable.');
    }

    const response = await fetch(
        `https://api.stripe.com/v1/payment_intents/${paymentIntentId}`,
        {
            headers: {
                Authorization: `Bearer ${secretKey}`,
            },
        },
    );

    const body = (await response.json()) as {
        amount?: number;
        error?: { message?: string };
    };

    if (!response.ok) {
        throw new Error(
            `Stripe PaymentIntent fetch failed (${response.status}): ${body.error?.message ?? 'unknown error'}`,
        );
    }

    return body.amount ?? 0;
}

export function expectedStripeAmountCents(proRata: number): number {
    return Math.round(roundMoney(proRata) * 100);
}

export async function getUpgradeDescriptionWithEngineCheck(
    api: ApiContext,
    email: string,
    request: UpgradeQuoteRequest,
): Promise<{
    status: number;
    body: UpgradeQuoteResponse;
    engine: BillingEngineQuote;
}> {
    const quote = await getUpgradeDescription(api, request);
    const engine = getBillingEngineQuote(email, request);

    if (quote.status === 200) {
        assertUpgradeQuoteMatchesEngine(quote.body, engine);
    }

    return { ...quote, engine };
}

export function expectedTermUpgradeCharge(
    currentPeriodTotal: number,
    newPeriodTotal: number,
    ratio: number,
): number {
    const credit = expectedMidCycleCharge(currentPeriodTotal, ratio);

    return roundMoney(Math.max(0, newPeriodTotal - credit));
}

function startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}
