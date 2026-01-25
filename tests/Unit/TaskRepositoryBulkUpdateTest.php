<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Repositories\TaskRepository;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Test TaskRepository::bulkUpdate() method
 */
class TaskRepositoryBulkUpdateTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    private TaskRepository $taskRepository;
    private Client $testClient;
    private Project $testProject;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        Model::reguard();

        $this->taskRepository = new TaskRepository();

        // Create test client
        $this->testClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        // Create test project
        $this->testProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
        ]);

        $this->testUser = User::factory()->create([
            'account_id' => $this->account->id,
        ]);
    }

    public function testBulkUpdateProjectIdUpdatesClientId()
    {
        // Create tasks with different clients
        $task1 = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'project_id' => null,
            'invoice_id' => null,
        ]);

        $task2 = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'project_id' => null,
            'invoice_id' => null,
        ]);

        // Create a different client and project
        $otherClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $otherProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $otherClient->id,
        ]);

        // Get the query builder for the tasks
        $models = Task::whereIn('id', [$task1->id, $task2->id]);

        // Bulk update project_id
        $this->taskRepository->bulkUpdate($models, 'project_id', $otherProject->hashed_id);

        // Refresh models from database
        $task1->refresh();
        $task2->refresh();

        // Assert both tasks now have the new project and client
        $this->assertEquals($otherProject->id, $task1->project_id);
        $this->assertEquals($otherClient->id, $task1->client_id);
        $this->assertEquals($otherProject->id, $task2->project_id);
        $this->assertEquals($otherClient->id, $task2->client_id);
    }

    public function testBulkUpdateProjectIdWithNonExistentProject()
    {
        // Create a task
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'project_id' => null,
            'invoice_id' => null,
        ]);

        $originalClientId = $task->client_id;
        $originalProjectId = $task->project_id;

        // Get the query builder for the task
        $models = Task::where('id', $task->id);

        // Try to bulk update with non-existent project ID
        $this->taskRepository->bulkUpdate($models, 'project_id', 99999);

        // Refresh model from database
        $task->refresh();

        // Assert task remains unchanged
        $this->assertEquals($originalClientId, $task->client_id);
        $this->assertEquals($originalProjectId, $task->project_id);
    }

    public function testBulkUpdateClientIdUnsetsProjectId()
    {
        // Create a task with a project
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'project_id' => $this->project->id,
            'invoice_id' => null,
        ]);

        // Create a different client
        $newClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        // Get the query builder for the task
        $models = Task::where('id', $task->id);

        // Bulk update client_id
        $this->taskRepository->bulkUpdate($models, 'client_id', $newClient->hashed_id);

        // Refresh model from database
        $task->refresh();

        // Assert client_id is updated and project_id is null
        $this->assertEquals($newClient->id, $task->client_id);
        $this->assertNull($task->project_id);
    }

    public function testBulkUpdateAssignedUser()
    {
        // Create tasks
        $task1 = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'assigned_user_id' => null,
            'invoice_id' => null,
        ]);

        $task2 = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'assigned_user_id' => null,
            'invoice_id' => null,
        ]);

        // Get the query builder for the tasks
        $models = Task::whereIn('id', [$task1->id, $task2->id]);

        // Bulk update assigned_user_id
        $this->taskRepository->bulkUpdate($models, 'assigned_user_id', $this->testUser->hashed_id);

        // Refresh models from database
        $task1->refresh();
        $task2->refresh();

        // Assert both tasks now have the assigned user
        $this->assertEquals($this->testUser->id, $task1->assigned_user_id);
        $this->assertEquals($this->testUser->id, $task2->assigned_user_id);
    }

    public function testBulkUpdateSkipsInvoicedTasks()
    {
        // Create an invoice first
        $invoice = \App\Models\Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
        ]);

        // Create tasks - one invoiced, one not
        $invoicedTask = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'assigned_user_id' => null,
            'invoice_id' => $invoice->id, // This task is invoiced
        ]);

        $regularTask = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'assigned_user_id' => null,
            'invoice_id' => null, // This task is not invoiced
        ]);

        // Get the query builder for both tasks
        $models = Task::whereIn('id', [$invoicedTask->id, $regularTask->id]);

        // Bulk update assigned_user_id
        $this->taskRepository->bulkUpdate($models, 'assigned_user_id', $this->testUser->hashed_id);

        // Refresh models from database
        $invoicedTask->refresh();
        $regularTask->refresh();

        // Assert invoiced task is unchanged
        $this->assertNull($invoicedTask->assigned_user_id);

        // Assert regular task is updated
        $this->assertEquals($this->testUser->id, $regularTask->assigned_user_id);
    }

    public function testBulkUpdateWithSoftDeletedProject()
    {
        // Create a task
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'project_id' => null,
            'invoice_id' => null,
        ]);

        // Soft delete the project
        $this->testProject->delete();

        // Get the query builder for the task
        $models = Task::where('id', $task->id);

        // Bulk update project_id (should work with soft deleted project)
        $this->taskRepository->bulkUpdate($models, 'project_id', $this->testProject->hashed_id);

        // Refresh model from database
        $task->refresh();

        // Assert task is updated with the soft deleted project
        $this->assertEquals($this->testProject->id, $task->project_id);
        $this->assertEquals($this->testClient->id, $task->client_id);
    }

    public function testBulkUpdateWithDifferentColumnTypes()
    {
        // Create a task
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'description' => 'Original Description',
            'rate' => 50.00,
            'invoice_id' => null,
        ]);

        // Test string column update
        $models = Task::where('id', $task->id);
        $this->taskRepository->bulkUpdate($models, 'description', 'New Description');
        $task->refresh();
        $this->assertEquals('New Description', $task->description);

        // Test numeric column update
        $models = Task::where('id', $task->id);
        $this->taskRepository->bulkUpdate($models, 'rate', 75.50);
        $task->refresh();
        $this->assertEquals(75.50, $task->rate);
    }

    public function testBulkUpdatePerformanceWithLargeDataset()
    {
        // Create many tasks
        $tasks = Task::factory()->count(100)->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'assigned_user_id' => null,
            'invoice_id' => null,
        ]);

        $taskIds = $tasks->pluck('id')->toArray();

        // Get the query builder for all tasks
        $models = Task::whereIn('id', $taskIds);

        // Measure execution time
        $startTime = microtime(true);

        // Bulk update assigned_user_id
        $this->taskRepository->bulkUpdate($models, 'assigned_user_id', $this->testUser->hashed_id);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert all tasks are updated
        $updatedTasks = Task::whereIn('id', $taskIds)->get();
        foreach ($updatedTasks as $task) {
            $this->assertEquals($this->testUser->id, $task->assigned_user_id);
        }

        // Assert execution time is reasonable (less than 1 second for 100 records)
        $this->assertLessThan(1.0, $executionTime, 'Bulk update should be fast for 100 records');
    }

    public function testBulkUpdateWithEmptyResultSet()
    {
        // Get query builder for non-existent tasks
        $models = Task::where('id', 99999);

        // This should not throw an error
        $this->taskRepository->bulkUpdate($models, 'assigned_user_id', $this->testUser->hashed_id);

        // No assertions needed - just ensuring no exceptions are thrown
        $this->assertTrue(true);
    }

    public function testBulkUpdateProjectIdWithTrashedProject()
    {
        // Create a task
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'project_id' => null,
            'invoice_id' => null,
        ]);

        // Soft delete the project
        $this->testProject->delete();

        // Get the query builder for the task
        $models = Task::where('id', $task->id);

        // Bulk update project_id (should work with soft deleted project)
        $this->taskRepository->bulkUpdate($models, 'project_id', $this->testProject->hashed_id);

        // Refresh model from database
        $task->refresh();

        // Assert task is updated with the soft deleted project
        $this->assertEquals($this->testProject->id, $task->project_id);
        $this->assertEquals($this->testClient->id, $task->client_id);
    }
}
