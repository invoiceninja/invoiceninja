<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Import\CSV;

use App\Factory\TaskFactory;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Import\Providers\Csv;
use App\Import\Transformer\BaseTransformer;
use App\Models\Task;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Import\Providers\Csv
 */
class TaskImportTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();

        $this->withoutExceptionHandling();

        auth()->login($this->user);
    }

    public function testTaskImportWithGroupedTaskNumbers()
    {
        Task::query()
            ->where('company_id', $this->company->id)
            ->forceDelete();

        $this->assertEquals(0, Task::withTrashed()->where('company_id', $this->company->id)->count());

        /*Need to import clients first*/
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/tasks2.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            0 => 'task.user_id',
            3 => 'project.name',
            2 => 'client.name',
            4 => 'task.number',
            5 => 'task.description',
            6 => 'task.billable',
            7 => 'task.start_date',
            9 => 'task.end_date',
            8 => 'task.start_time',
            10 => 'task.end_time',
            11 => 'task.duration',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['task' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash.'-task', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $this->assertInstanceOf(Csv::class, $csv_importer);

        $csv_importer->import('task');

        $base_transformer = new BaseTransformer($this->company);

        $task = Task::where('company_id', $this->company->id)->where('number', 'x1234')->first();
        $this->assertNotNull($task);
        $this->assertEquals(1998, $task->calcDuration());

        $time_log = json_decode($task->time_log);

        foreach ($time_log as $log) {
            $this->assertTrue($log[3]);
        }

        // x1233 spans two CSV rows (Bob 13:57:17→14:39:11 and James 14:29:25→16:31:24)
        // whose time entries overlap. StoreTaskRequest's time_log validator rejects the
        // grouped record, so it is captured in error_array and not persisted.
        $this->assertNull(Task::where('company_id', $this->company->id)->where('number', 'x1233')->first());

        $errors = $csv_importer->error_array['task'] ?? [];
        $hasOverlapError = false;
        foreach ($errors as $entry) {
            $payload = $entry['invoice'] ?? $entry['task'] ?? [];
            if (($payload['number'] ?? null) !== 'x1233') {
                continue;
            }
            $messages = $entry['error'] ?? [];
            $messages = is_array($messages) ? $messages : [$messages];
            foreach ($messages as $msg) {
                if (str_contains($msg, 'overlapping')) {
                    $hasOverlapError = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($hasOverlapError, 'Expected overlap validation error for x1233');
    }



    public function testRunFormRequestResolvesSubclassRules()
    {
        $instance = StoreTaskRequest::runFormRequest([
            'number' => 'rfr-test-' . uniqid(),
        ]);

        $this->assertArrayHasKey('number', $instance->getRules());
    }

    public function testRunFormRequestDetectsDuplicateTaskNumber()
    {
        $this->user->setCompany($this->company);

        $existing = TaskFactory::create($this->company->id, $this->user->id);
        $existing->number = 'duplicate-rfr-1';
        $existing->save();

        $instance = StoreTaskRequest::runFormRequest([
            'number' => 'duplicate-rfr-1',
        ]);

        $this->assertTrue($instance->fails());
        $this->assertContains('The number has already been taken.', $instance->errors()->all());
    }

    public function testTaskImportSkipsDuplicateNumbers()
    {
        Task::query()
            ->where('company_id', $this->company->id)
            ->forceDelete();

        $existing = TaskFactory::create($this->company->id, $this->user->id);
        $existing->number = 'x1234';
        $existing->save();

        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/tasks2.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            0 => 'task.user_id',
            3 => 'project.name',
            2 => 'client.name',
            4 => 'task.number',
            5 => 'task.description',
            6 => 'task.billable',
            7 => 'task.start_date',
            9 => 'task.end_date',
            8 => 'task.start_time',
            10 => 'task.end_time',
            11 => 'task.duration',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['task' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash.'-task', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);
        $csv_importer->import('task');

        $this->assertEquals(1, Task::where('company_id', $this->company->id)->where('number', 'x1234')->count());

        $errors = $csv_importer->error_array['task'] ?? [];
        $hasUniqueError = false;
        foreach ($errors as $entry) {
            $messages = $entry['error'] ?? [];
            $messages = is_array($messages) ? $messages : [$messages];
            foreach ($messages as $msg) {
                if (str_contains($msg, 'number has already been taken')) {
                    $hasUniqueError = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($hasUniqueError, 'Expected unique-number validation error in import errors');
    }

    public function testTaskImport()
    {
        Task::query()
            ->where('company_id', $this->company->id)
            ->forceDelete();

        $this->assertEquals(0, Task::withTrashed()->where('company_id', $this->company->id)->count());

        /*Need to import clients first*/
        $csv = file_get_contents(
            base_path().'/tests/Feature/Import/tasks.csv'
        );
        $hash = Str::random(32);
        $column_map = [
            0 => 'task.user_id',
            3 => 'project.name',
            2 => 'client.name',
            5 => 'task.description',
            6 => 'task.billable',
            7 => 'task.start_date',
            9 => 'task.end_date',
            8 => 'task.start_time',
            10 => 'task.end_time',
            11 => 'task.duration',
        ];

        $data = [
            'hash' => $hash,
            'column_map' => ['task' => ['mapping' => $column_map]],
            'skip_header' => true,
            'import_type' => 'csv',
        ];

        Cache::put($hash.'-task', base64_encode($csv), 360);

        $csv_importer = new Csv($data, $this->company);

        $this->assertInstanceOf(Csv::class, $csv_importer);

        $csv_importer->import('task');

        $base_transformer = new BaseTransformer($this->company);

    }


}
