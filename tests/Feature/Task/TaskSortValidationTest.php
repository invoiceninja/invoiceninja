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

namespace Tests\Feature\Task;

use App\Factory\CompanyUserFactory;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Models\Account;
use App\Models\Company;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @see App\Http\Controllers\TaskController::sort()
 * @see App\Http\Requests\Task\SortTaskRequest
 */
class TaskSortValidationTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testTaskSortUpdatesColumnsAndTaskPlacement(): void
    {
        $first_status = TaskStatus::query()
            ->where('company_id', $this->company->id)
            ->orderBy('id')
            ->firstOrFail();

        $second_status = TaskStatus::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'SortColumn_' . uniqid(),
        ]);

        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => $first_status->id,
            'status_order' => 0,
        ]);

        $response = $this->postTaskSort([
            'status_ids' => [
                $second_status->hashed_id,
                $first_status->hashed_id,
            ],
            'task_ids' => [
                $second_status->hashed_id => [],
                $first_status->hashed_id => [$task->hashed_id],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertSame(0, $second_status->fresh()->status_order);
        $this->assertSame(1, $first_status->fresh()->status_order);

        $task->refresh();
        $this->assertSame($first_status->id, $task->status_id);
        $this->assertSame(0, $task->status_order);
    }

    public function testTaskSortRejectsForeignCompanyStatusKey(): void
    {
        $foreign_account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);
        $foreign_account->num_users = 3;
        $foreign_account->save();

        $foreign_company = Company::factory()->create([
            'account_id' => $foreign_account->id,
        ]);

        $foreign_user = User::factory()->create([
            'account_id' => $foreign_account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('foreign') . '@gmail.com',
        ]);

        CompanyUserFactory::create($foreign_user->id, $foreign_company->id, $foreign_account->id)->save();
        CreateCompanyTaskStatuses::dispatchSync($foreign_company, $foreign_user);

        $foreign_status = TaskStatus::query()
            ->where('company_id', $foreign_company->id)
            ->firstOrFail();

        $local_status = TaskStatus::query()
            ->where('company_id', $this->company->id)
            ->firstOrFail();

        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => $local_status->id,
        ]);

        $original_status_id = $task->status_id;

        $response = $this->postTaskSort([
            'status_ids' => [
                $local_status->hashed_id,
            ],
            'task_ids' => [
                $foreign_status->hashed_id => [$task->hashed_id],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertSame($original_status_id, $task->fresh()->status_id);
    }

    public function testTaskSortRejectsForeignStatusInStatusIds(): void
    {
        $foreign_account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $foreign_company = Company::factory()->create([
            'account_id' => $foreign_account->id,
        ]);

        $foreign_user = User::factory()->create([
            'account_id' => $foreign_account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('foreign') . '@gmail.com',
        ]);

        CompanyUserFactory::create($foreign_user->id, $foreign_company->id, $foreign_account->id)->save();
        CreateCompanyTaskStatuses::dispatchSync($foreign_company, $foreign_user);

        $foreign_status = TaskStatus::query()
            ->where('company_id', $foreign_company->id)
            ->firstOrFail();

        $local_status = TaskStatus::query()
            ->where('company_id', $this->company->id)
            ->firstOrFail();

        $response = $this->postTaskSort([
            'status_ids' => [
                $foreign_status->hashed_id,
                $local_status->hashed_id,
            ],
            'task_ids' => [
                $local_status->hashed_id => [],
            ],
        ]);

        $response->assertStatus(422);
    }

    private function postTaskSort(array $payload)
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tasks/sort', $payload);
    }
}
