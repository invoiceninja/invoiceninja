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

namespace App\Console\Commands;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Services\Report\ReconcilePaymentApplicationTransactionEventsRunner;
use App\Utils\Ninja;
use Illuminate\Console\Command;

class ReconcileCashEvents extends Command
{
    protected $signature = 'ninja:reconcile-cash-events
                            {--fix : Apply remediations only when exact cash representation is provable (default: report only)}
                            {--v2-only : With --fix, only v2 drift / missing source remediations}
                            {--legacy-only : With --fix, only eligible legacy payment_history date sync}
                            {--payment=* : Optional limit to payment id(s) within each company scan}
                            {--db= : Limit to one database connection name (multi-DB self-host installs; default: all shards)}
                            {--json : Output full results as JSON}';

    protected $description = 'Self-host only: scan all companies on each database shard for paymentables vs cash transaction_events.';

    public function handle(ReconcilePaymentApplicationTransactionEventsRunner $runner): int
    {
        if (Ninja::isHosted()) {
            $this->emitError('ninja:reconcile-cash-events is not available on Invoice Ninja hosted.');

            return self::FAILURE;
        }

        $json_output = (bool) $this->option('json');
        $fix = (bool) $this->option('fix');
        $v2_only = (bool) $this->option('v2-only');
        $legacy_only = (bool) $this->option('legacy-only');

        if ($v2_only && $legacy_only) {
            $this->emitError('Use at most one of --v2-only and --legacy-only.');

            return self::FAILURE;
        }

        $payment_filter = $this->resolveStrictPaymentIdFilter();

        if ($payment_filter === false) {
            return self::FAILURE;
        }

        $remediate_v2 = $fix && ! $legacy_only;
        $remediate_legacy = $fix && ! $v2_only;

        if (! $json_output) {
            if (! $fix) {
                $this->info('Report only (no writes). Pass --fix to apply gated remediations.');
            } elseif ($remediate_v2 && $remediate_legacy) {
                $this->warn('Fix mode: v2 and legacy only when post-verify succeeds.');
            } elseif ($remediate_v2) {
                $this->warn('Fix mode: v2 only.');
            } else {
                $this->warn('Fix mode: legacy dates only.');
            }
        }

        $current_db = config('database.default');
        $databases = $this->resolveDatabases($current_db);

        if ($databases === false) {
            return self::FAILURE;
        }

        $totals = [
            'companies_scanned' => 0,
            'companies_with_problems' => 0,
            'problems' => 0,
            'remediated' => 0,
            'legacy_dates_synced' => 0,
            'fix_failed' => 0,
            'repaired' => 0,
        ];

        $all_results = [];

        try {
            foreach ($databases as $db) {
                MultiDB::setDB($db);

                if (! $json_output) {
                    $this->line('');
                    $this->info("Database: {$db}");
                }

                foreach (Company::query()->orderBy('id')->lazyById(100) as $company) {
                    $totals['companies_scanned']++;
                    $result = $runner->runForCompany(
                        $company,
                        $remediate_v2,
                        $remediate_legacy,
                        $payment_filter,
                    );
                    $summary = $result['summary'];
                    $problems = $result['problems'];
                    $repairs = $result['repairs'];

                    if ($summary['problem_count'] > 0) {
                        $totals['companies_with_problems']++;
                        $totals['problems'] += $summary['problem_count'];
                    }

                    $totals['remediated'] += (int) $summary['remediated'];
                    $totals['legacy_dates_synced'] += (int) $summary['legacy_dates_synced'];
                    $totals['fix_failed'] += (int) ($summary['fix_failed'] ?? 0);
                    $totals['repaired'] += count($repairs);

                    $had_activity = $summary['problem_count'] > 0
                        || count($repairs) > 0
                        || ($summary['fix_failed'] ?? 0) > 0;

                    if ($had_activity) {
                        $all_results[] = [
                            'database' => $db,
                            'summary' => $summary,
                            'problems' => $problems,
                            'repairs' => $repairs,
                        ];
                    }

                    if ($json_output) {
                        continue;
                    }

                    $this->renderCompanyResult($company->id, $summary, $problems, $repairs, $fix);
                }
            }
        } finally {
            MultiDB::setDB($current_db);
        }

        $exit_code = ($fix && $totals['fix_failed'] > 0) ? self::FAILURE : self::SUCCESS;

        if ($json_output) {
            $this->getOutput()->writeln(json_encode([
                'dry_run' => ! $fix,
                'remediate_v2' => $remediate_v2,
                'remediate_legacy' => $remediate_legacy,
                'filters' => [
                    'all_companies' => true,
                    'payment_ids' => $payment_filter,
                    'database' => $this->option('db'),
                ],
                'totals' => $totals,
                'companies' => $all_results,
            ], JSON_PRETTY_PRINT));

            return $exit_code;
        }

        $this->line('');
        $this->table(
            ['Metric', 'Count'],
            collect($totals)->map(fn ($value, $key) => [$key, $value])->values()->all(),
        );

        return $exit_code;
    }

    /**
     * @return list<int>|null|false null = no payment filter; false = invalid input
     */
    private function resolveStrictPaymentIdFilter(): array|null|false
    {
        $values = $this->option('payment');

        if (! is_array($values)) {
            return null;
        }

        $tokens = collect($values)
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim((string) $value))
            ->all();

        if ($tokens === []) {
            return null;
        }

        $invalid = [];
        $valid = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                $invalid[] = '(empty)';

                continue;
            }

            if (! ctype_digit($token)) {
                $invalid[] = $token;

                continue;
            }

            $id = (int) $token;

            if ($id <= 0) {
                $invalid[] = $token;

                continue;
            }

            $valid[] = $id;
        }

        if ($invalid !== []) {
            $this->emitError('Invalid --payment value(s): '.implode(', ', $invalid));

            return false;
        }

        return array_values(array_unique($valid));
    }

    /**
     * @return list<string>|false
     */
    private function resolveDatabases(string $current_db): array|false
    {
        $db_option = $this->option('db');

        if (is_string($db_option) && $db_option !== '') {
            $allowed = config('ninja.db.multi_db_enabled')
                ? MultiDB::$dbs
                : [$current_db];

            if (! in_array($db_option, $allowed, true)) {
                $this->emitError("Unknown --db connection: {$db_option}");

                return false;
            }

            return [$db_option];
        }

        return config('ninja.db.multi_db_enabled')
            ? MultiDB::$dbs
            : [$current_db];
    }

    private function emitError(string $message): void
    {
        if ((bool) $this->option('json')) {
            $this->getOutput()->writeln(json_encode([
                'error' => $message,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->error($message);
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $problems
     * @param  list<array<string, mixed>>  $repairs
     */
    private function renderCompanyResult(
        int $company_id,
        array $summary,
        array $problems,
        array $repairs,
        bool $fix,
    ): void {
        if ($summary['problem_count'] === 0 && $repairs === [] && ($summary['fix_failed'] ?? 0) === 0) {
            return;
        }

        if ($summary['problem_count'] === 0 && $repairs !== []) {
            $this->line("  Company {$company_id}: repaired ".count($repairs).", scanned {$summary['scanned']}");

            foreach ($repairs as $repair) {
                $this->line(sprintf(
                    '    [repaired:%s] paymentable=%d event=%d %s → %s',
                    $repair['issue'],
                    $repair['paymentable_id'],
                    $repair['event_id'],
                    $repair['ledger_date_before'] ?? '-',
                    $repair['ledger_date_after'],
                ));
            }

            return;
        }

        $this->line("  Company {$company_id}: {$summary['problem_count']} issue(s), scanned {$summary['scanned']}");

        foreach ($problems as $problem) {
            $outcome = $problem['outcome'] ?? 'detected';
            $this->line(sprintf(
                '    [%s/%s] paymentable=%d invoice=%d ledger=%s intended=%s%s',
                $problem['issue'],
                $outcome,
                $problem['paymentable_id'],
                $problem['invoice_id'],
                $problem['ledger_effective_date'] ?? '-',
                $problem['intended'],
                isset($problem['skip_reason']) && $problem['skip_reason']
                    ? " ({$problem['skip_reason']})"
                    : '',
            ));
        }

        if ($fix && $repairs !== []) {
            foreach ($repairs as $repair) {
                $this->line(sprintf(
                    '    [repaired:%s] paymentable=%d event=%d %s → %s',
                    $repair['issue'],
                    $repair['paymentable_id'],
                    $repair['event_id'],
                    $repair['ledger_date_before'] ?? '-',
                    $repair['ledger_date_after'],
                ));
            }
        }
    }
}
