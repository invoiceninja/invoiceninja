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

        foreach($time_log as $log) {
            $this->assertTrue($log[3]);
        }

        $task = Task::where('company_id', $this->company->id)->where('number', 'x1233')->first();
        $this->assertNotNull($task);
        $this->assertEquals(9833, $task->calcDuration());

        $time_log = json_decode($task->time_log);

        foreach($time_log as $log) {
            $this->assertTrue($log[3]);
        }


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
