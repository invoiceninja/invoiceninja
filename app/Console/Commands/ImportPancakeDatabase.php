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

use App\Import\Pancake\ApiClient;
use App\Import\Pancake\DatabaseEntity;
use App\Import\Pancake\DatabaseImporter;
use App\Import\Pancake\DatabaseSource;
use App\Import\Pancake\ImportState;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImportPancakeDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:import-pancake-db
                            {api_token : Invoice Ninja API token used to create records}
                            {--source-connection=pancake : Runtime or configured Pancake database connection name}
                            {--source-url= : Pancake MariaDB connection URL}
                            {--source-host= : Pancake database host}
                            {--source-port= : Pancake database port}
                            {--source-database= : Pancake database name}
                            {--source-username= : Pancake database username}
                            {--source-password= : Pancake database password}
                            {--source-socket= : Pancake database Unix socket}
                            {--table-prefix=pancake_ : Prefix used by Pancake tables}
                            {--api-url= : Invoice Ninja base URL; defaults to APP_URL}
                            {--business-identity= : Pancake business identity ID to import; use 0 for unassigned clients}
                            {--entities= : Comma-separated entity list; omit to import all supported data}
                            {--state= : JSON checkpoint path; defaults below storage/app/pancake-import}
                            {--files-root= : Root directory for relative Pancake attachment paths}
                            {--skip-attachments : Do not upload company logos, files, or receipts}
                            {--dry-run : Transform and validate references without calling the API or writing state}
                            {--restart : Delete the matching checkpoint before importing}
                            {--abort-on-failure : Stop after the first failed source record}
                            {--timeout=60 : Per-request API timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import a Pancake MariaDB database into Invoice Ninja through the API';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly DatabaseSource $source,
        private readonly DatabaseImporter $importer,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $api_token = trim((string) $this->argument('api_token'));

            if ($api_token === '') {
                throw new InvalidArgumentException('An Invoice Ninja API token is required.');
            }

            $api_url = rtrim($this->optionString('api-url') ?: (string) config('app.url'), '/');

            if (! filter_var($api_url, FILTER_VALIDATE_URL) || ! preg_match('/^https?:\/\//i', $api_url)) {
                throw new InvalidArgumentException('The Invoice Ninja --api-url must be a valid HTTP or HTTPS URL.');
            }

            $prefix = (string) ($this->option('table-prefix') ?? 'pancake_');

            if (! preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
                throw new InvalidArgumentException('The Pancake --table-prefix may contain only letters, numbers, and underscores.');
            }

            $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 3600],
            ]);

            if ($timeout === false) {
                throw new InvalidArgumentException('The --timeout value must be between 1 and 3600 seconds.');
            }

            $identity = $this->nonNegativeIntegerOption('business-identity');
            $entities = DatabaseEntity::fromOption($this->optionString('entities'));

            if ($this->option('skip-attachments')) {
                $entities = array_values(array_filter(
                    $entities,
                    fn(DatabaseEntity $entity): bool => $entity !== DatabaseEntity::Documents,
                ));
            }

            $connection_name = $this->configureSourceConnection();
            $files_root = $this->optionString('files-root');

            if ($files_root !== null && ! is_dir($files_root)) {
                throw new InvalidArgumentException("The Pancake --files-root directory does not exist: {$files_root}");
            }

            $this->source->configure($connection_name, $prefix, null, $files_root);

            if ($this->option('skip-attachments')) {
                $this->source->withoutAttachments();
            }

            $identity = $this->source->resolveBusinessIdentity($identity);
            $this->source->useBusinessIdentity($identity);
            $this->source->validate($entities);

            $state_path = $this->optionString('state') ?: $this->defaultStatePath($connection_name, $identity);
            $api = new ApiClient($api_token, $api_url, (int) $timeout);

            if (! $this->option('dry-run')) {
                $api->verify();
            }

            $state = new ImportState(
                $state_path,
                $this->source->fingerprint($api_url, $api_token),
                ! (bool) $this->option('dry-run'),
                (bool) $this->option('restart'),
            );

            $this->components->info(sprintf(
                '%s Pancake database import for %d entity type(s)%s.',
                $this->option('dry-run') ? 'Validating' : 'Starting',
                count($entities),
                $identity !== null ? " (business identity {$identity})" : '',
            ));
            $this->components->warn(
                'Recurring templates are created as drafts. Pancake installment schedules are retained in invoice notes; they are not activated as billing jobs.',
            );
            $this->components->warn(
                'Pancake staff users are imported first without their legacy passwords. Inactive users are locked; active users must set an Invoice Ninja password.',
            );

            $result = $this->importer->import(
                $this->source,
                $api,
                $state,
                $entities,
                (bool) $this->option('dry-run'),
                (bool) $this->option('abort-on-failure'),
                function (string $type, string $message, array $context): void {
                    if ($type === 'failure') {
                        $this->components->error($message);
                    } elseif ($this->output->isVerbose()) {
                        $this->line($message);

                        if ($this->output->isVeryVerbose() && isset($context['payload'])) {
                            $this->line(json_encode($context['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        }
                    }
                },
            );

            $this->table(
                ['Entity', 'Created', 'Reused', 'Resumed', 'Failed'],
                array_map(
                    fn(string $entity, array $counts): array => [
                        $entity,
                        $counts['created'],
                        $counts['reused'],
                        $counts['skipped'],
                        $counts['failed'],
                    ],
                    array_keys($result['entities']),
                    array_values($result['entities']),
                ),
            );

            $unsupported = $this->source->unsupportedCounts();

            if ($unsupported !== []) {
                $this->components->warn('Pancake rows without a safe Invoice Ninja API equivalent were not imported:');
                $this->table(
                    ['Source table', 'Rows'],
                    array_map(
                        fn(string $table, int $count): array => [$table, $count],
                        array_keys($unsupported),
                        array_values($unsupported),
                    ),
                );
            }

            $this->components->info(sprintf(
                '%s: %d created, %d reused, %d resumed, %d failed.',
                $this->option('dry-run') ? 'Dry run complete; no API records or checkpoint were written' : 'Pancake import complete',
                $result['created'],
                $result['reused'],
                $result['skipped'],
                $result['failed'],
            ));

            if (! $this->option('dry-run')) {
                $this->line("Checkpoint: {$state_path}");
            }

            return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error('Pancake import could not start: ' . $exception->getMessage());

            if ($this->output->isVeryVerbose()) {
                throw $exception;
            }

            return self::FAILURE;
        }
    }

    private function configureSourceConnection(): string
    {
        $connection_name = $this->optionString('source-connection') ?: 'pancake';
        $configured = config("database.connections.{$connection_name}");
        $has_inline_configuration = collect([
            'source-url',
            'source-host',
            'source-port',
            'source-database',
            'source-username',
            'source-password',
            'source-socket',
        ])->contains(fn(string $option): bool => $this->option($option) !== null && $this->option($option) !== '');

        if (! is_array($configured) && ! $has_inline_configuration) {
            throw new InvalidArgumentException(
                "Database connection [{$connection_name}] is not configured. Supply --source-database and connection options, --source-url, or configure database.connections.{$connection_name}.",
            );
        }

        if (! $has_inline_configuration) {
            return $connection_name;
        }

        $base = is_array($configured) ? $configured : config('database.connections.mysql', []);

        if (! is_array($base)) {
            $base = [];
        }

        $overrides = [
            'driver' => 'mysql',
            'url' => $this->optionString('source-url'),
            'host' => $this->optionString('source-host'),
            'port' => $this->optionString('source-port'),
            'database' => $this->optionString('source-database'),
            'username' => $this->optionString('source-username'),
            'unix_socket' => $this->optionString('source-socket'),
        ];

        if ($this->option('source-password') !== null) {
            $overrides['password'] = (string) $this->option('source-password');
        }

        $connection = array_replace($base, array_filter(
            $overrides,
            fn(mixed $value): bool => $value !== null,
        ));

        if (($connection['url'] ?? null) === null && trim((string) ($connection['database'] ?? '')) === '') {
            throw new InvalidArgumentException('A Pancake source database name or --source-url is required.');
        }

        config()->set("database.connections.{$connection_name}", $connection);
        $this->database->purge($connection_name);

        return $connection_name;
    }

    private function defaultStatePath(string $connection_name, ?int $identity): string
    {
        $database = (string) config("database.connections.{$connection_name}.database", $connection_name);
        $database = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $database) ?: 'pancake';
        $identity_key = $identity === null ? 'all' : (string) $identity;

        return storage_path("app/pancake-import/{$database}-{$identity_key}.json");
    }

    private function nonNegativeIntegerOption(string $name): ?int
    {
        $value = $this->optionString($name);

        if ($value === null) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($integer === false) {
            throw new InvalidArgumentException("The --{$name} value must be zero or a positive integer.");
        }

        return (int) $integer;
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
