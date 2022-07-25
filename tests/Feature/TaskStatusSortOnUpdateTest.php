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

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class TaskStatusSortOnUpdateTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use MakesHash;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    // public function testTasksSort()
    // {

    //     $project = Project::factory()->create([
    //         'user_id' => $this->user->id,
    //         'company_id' => $this->company->id,
    //         'name' => 'Test Project',
    //     ]);

    //     for($x=0; $x<10; $x++)
    //     {
    //         $task = Task::factory()->create([
    //             'user_id' => $this->user->id,
    //             'company_id' => $this->company->id,
    //             'project_id' => $project->id
    //         ]);

    //         $task->status_id = TaskStatus::where('company_id', $this->company->id)->first()->id;
    //         $task->save();
    //     }

    //     $this->assertTrue($task->project()->exists());
    //     $this->assertEquals($task->project->tasks->count(), 10);

    //     $task->status_order = 1;

    //     $response = $this->withHeaders([
    //             'X-API-SECRET' => config('ninja.api_secret'),
    //             'X-API-TOKEN' => $this->token,
    //         ])->put('/api/v1/tasks/'.$this->encodePrimaryKey($task->id), $task->toArray());

    //     $response->assertStatus(200);

    //     $this->assertEquals($task->fresh()->status_order, 1);

    //     $task->status_order = 10;

    //     $response = $this->withHeaders([
    //             'X-API-SECRET' => config('ninja.api_secret'),
    //             'X-API-TOKEN' => $this->token,
    //         ])->put('/api/v1/tasks/'.$this->encodePrimaryKey($task->id), $task->toArray());

    //     $response->assertStatus(200);

    //     nlog($task->fresh()->project->tasks->toArray());

    //     $this->assertEquals($task->fresh()->status_order, 9);

    // }
}
