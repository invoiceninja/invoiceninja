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

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Client;
use App\Models\Project;
use App\Factory\TaskFactory;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Repositories\TaskRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Test that an explicit $0 task rate persists on new tasks, while an
 * omitted rate still inherits the project/client/company default.
 *
 */
class TaskRatePersistenceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    private TaskRepository $taskRepository;
    private Client $testClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        Model::reguard();

        $this->taskRepository = new TaskRepository();

        // Give the company a non-zero default task rate so the fallback,
        // if it fires, is clearly distinguishable from an explicit $0.
        $settings = $this->company->settings;
        $settings->default_task_rate = 100;
        $this->company->settings = $settings;
        $this->company->save();

        $this->testClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
    }

    private function newTask(): Task
    {
        return TaskFactory::create($this->company->id, $this->user->id);
    }

    public function testExplicitZeroRatePersistsOnNewTask()
    {
        $data = [
            'client_id' => $this->testClient->id,
            'rate' => 0,
        ];

        $task = $this->taskRepository->save($data, $this->newTask());
        $task->refresh();

        // The explicit $0 must survive, not be overwritten by the
        // company default_task_rate (100).
        $this->assertEquals(0, $task->rate);
    }

    public function testOmittedRateStillInheritsCompanyDefault()
    {
        $data = [
            'client_id' => $this->testClient->id,
            // no 'rate' key at all
        ];

        $task = $this->taskRepository->save($data, $this->newTask());
        $task->refresh();

        // With no rate supplied, the fallback should apply the company default.
        $this->assertEquals(100, $task->rate);
    }

    public function testExplicitNonZeroRatePersistsOnNewTask()
    {
        $data = [
            'client_id' => $this->testClient->id,
            'rate' => 42.5,
        ];

        $task = $this->taskRepository->save($data, $this->newTask());
        $task->refresh();

        $this->assertEquals(42.5, $task->rate);
    }

    public function testExplicitZeroRateOverridesProjectRate()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'task_rate' => 150,
        ]);

        $data = [
            'client_id' => $this->testClient->id,
            'project_id' => $project->id,
            'rate' => 0,
        ];

        $task = $this->taskRepository->save($data, $this->newTask());
        $task->refresh();

        // Explicit $0 must win even when the project carries a task_rate.
        $this->assertEquals(0, $task->rate);
    }

    public function testOmittedRateInheritsProjectRate()
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'task_rate' => 150,
        ]);

        $data = [
            'client_id' => $this->testClient->id,
            'project_id' => $project->id,
            // no 'rate' key
        ];

        $task = $this->taskRepository->save($data, $this->newTask());
        $task->refresh();

        $this->assertEquals(150, $task->rate);
    }
}
