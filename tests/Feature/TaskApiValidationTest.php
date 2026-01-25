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

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Test Task API validation and status codes
 */
class TaskApiValidationTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    private Client $testClient;
    private Project $testProject;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();
        Model::reguard();

        // Create test data
        $this->testClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->testProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
        ]);

        $this->testUser = User::factory()->create([
            'account_id' => $this->account->id,
        ]);
    }

    public function testTimeLogValidation()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task Description',
            'time_log' => json_encode([
                [
                    "billable" => true,
                    "date" => "2025-10-31",
                    "end_time" => "16:00:00",
                    "start_time" => "08:00:00"
                ]
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        nlog($response->json());
        
    }
    // ==================== VALID PAYLOADS (200 STATUS) ====================

    public function testCreateTaskWithValidPayloadReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task Description',
            'time_log' => json_encode([
                [time() - 3600, time(), 'Working on task', true]
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'description',
                'client_id',
                'time_log',
            ]
        ]);
    }

    public function testCreateTaskWithProjectReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'project_id' => $this->testProject->hashed_id,
            'description' => 'Test Task with Project',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'description' => 'Test Task with Project',
                'project_id' => $this->testProject->hashed_id,
            ]
        ]);
    }

    public function testUpdateTaskWithValidPayloadReturns200()
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'description' => 'Original Description',
        ]);

        $data = [
            'description' => 'Updated Description',
            'time_log' => json_encode([
                [time() - 1800, time(), 'Updated time log', true]
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}", $data);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'description' => 'Updated Description',
            ]
        ]);
    }

    public function testCreateTaskWithValidTimeLogArrayReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Array Time Log',
            'time_log' => [
                [time() - 3600, time() - 1800, 'Working on task', true],
                [time() - 1800, time() - 900, 'Break time', false],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
    }

    public function testCreateTaskWithEmptyTimeLogReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Empty Time Log',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
    }

    // ==================== INVALID PAYLOADS (422 STATUS) ====================

    public function testCreateTaskWithoutClientIdReturns200()
    {
        $data = [
            'description' => 'Test Task without Client',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'description',
                'client_id', // Should be null
            ]
        ]);
    }

    public function testCreateTaskWithInvalidClientIdReturns422()
    {
        $data = [
            'client_id' => 'invalid-client-id',
            'description' => 'Test Task with Invalid Client',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function testCreateTaskWithNonExistentClientIdReturns422()
    {
        $data = [
            'client_id' => $this->encodePrimaryKey(99999),
            'description' => 'Test Task with Non-existent Client',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function testCreateTaskWithInvalidProjectIdReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'project_id' => 'invalid-project-id',
            'description' => 'Test Task with Invalid Project',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        // Invalid project_id should be silently removed
        $response->assertJsonMissing(['project_id']);
    }

    public function testCreateTaskWithNonExistentProjectIdReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'project_id' => $this->encodePrimaryKey(99999),
            'description' => 'Test Task with Non-existent Project',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        // Non-existent project_id should be silently removed
        $response->assertJsonMissing(['project_id']);
    }

    // ==================== TIME_LOG VALIDATION TESTS ====================

    public function testCreateTaskWithInvalidTimeLogFormatReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Invalid Time Log',
            'time_log' => 'invalid-json-string',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        // Invalid JSON should be converted to empty array
        $response->assertJson([
            'data' => [
                'time_log' => '[]'
            ]
        ]);
    }

    public function testCreateTaskWithTimeLogTooManyElementsReturns422()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Too Many Time Log Elements',
            'time_log' => json_encode([
                [time() - 3600, time(), 'Working', true, 'extra-element']
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['time_log']);
    }

    public function testCreateTaskWithNonIntegerTimestampsReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Non-integer Timestamps',
            'time_log' => json_encode([
                ['not-a-timestamp', time(), 'Working', true]
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        // Non-integer timestamps should be converted to integers
        $response->assertJsonStructure([
            'data' => [
                'time_log'
            ]
        ]);
    }

    public function testCreateTaskWithNonBooleanBillableFlagReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Non-boolean Billable',
            'time_log' => json_encode([
                [time() - 3600, time(), 'Working', 'not-a-boolean']
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        // Non-boolean billable flag should be converted to true
        $response->assertJsonStructure([
            'data' => [
                'time_log'
            ]
        ]);
    }

    public function testCreateTaskWithOverlappingTimeLogReturns422()
    {
        $startTime = time() - 3600;
        $endTime = time() - 1800;
        $overlapStart = time() - 2700; // Overlaps with first entry
        $overlapEnd = time() - 900;

        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Overlapping Time Log',
            'time_log' => json_encode([
                [$startTime, $endTime, 'First session', true],
                [$overlapStart, $overlapEnd, 'Overlapping session', true],
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['time_log']);
    }

    public function testCreateTaskWithStartTimeAfterEndTimeReturns422()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Invalid Time Order',
            'time_log' => json_encode([
                [time(), time() - 3600, 'Start after end', true] // Start time after end time
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['time_log']);
    }

    // ==================== UPDATE VALIDATION TESTS ====================

    public function testUpdateTaskWithInvalidClientIdReturns422()
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
        ]);

        $data = [
            'client_id' => 'invalid-client-id',
            'description' => 'Updated Description',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function testUpdateTaskWithInvalidProjectIdReturns200()
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
        ]);

        $data = [
            'project_id' => 'invalid-project-id',
            'description' => 'Updated Description',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}", $data);

        $response->assertStatus(200);
        // Invalid project_id should be silently removed
        $response->assertJsonMissing(['project_id']);
    }

    public function testUpdateTaskWithInvalidTimeLogReturns200()
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
        ]);

        $data = [
            'time_log' => json_encode([
                [time() - 3600, time(), 'Working', 'not-a-boolean']
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/tasks/{$task->hashed_id}", $data);

        $response->assertStatus(200);
        // Invalid data should be sanitized
        $response->assertJsonStructure([
            'data' => [
                'time_log'
            ]
        ]);
    }

    // ==================== EDGE CASES ====================

    public function testCreateTaskWithProjectFromDifferentClientReturns200()
    {
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

        $data = [
            'client_id' => $this->testClient->hashed_id,
            'project_id' => $otherProject->hashed_id, // Project belongs to different client
            'description' => 'Test Task with Mismatched Project',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
        // Project from different client should be silently removed
        $response->assertJsonMissing(['project_id']);
    }

    public function testCreateTaskWithDeletedClientReturns422()
    {
        $deletedClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => true,
        ]);

        $data = [
            'client_id' => $deletedClient->hashed_id,
            'description' => 'Test Task with Deleted Client',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function testCreateTaskWithDeletedProjectReturns422()
    {
        $deletedProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->testClient->id,
            'is_deleted' => true,
        ]);

        $data = [
            'client_id' => $this->testClient->hashed_id,
            'project_id' => $deletedProject->hashed_id,
            'description' => 'Test Task with Deleted Project',
            'time_log' => json_encode([]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_id']);
    }

    public function testCreateTaskWithValidTimeLogWithZeroEndTimeReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Running Timer',
            'time_log' => json_encode([
                [time() - 3600, 0, 'Currently running', true] // 0 means timer is running
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
    }

    public function testCreateTaskWithValidTimeLogWithOnlyTwoElementsReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Minimal Time Log',
            'time_log' => json_encode([
                [time() - 3600, time()] // Only start and end time
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
    }

    public function testCreateTaskWithValidTimeLogWithThreeElementsReturns200()
    {
        $data = [
            'client_id' => $this->testClient->hashed_id,
            'description' => 'Test Task with Three Element Time Log',
            'time_log' => json_encode([
                [time() - 3600, time(), 'Working on task'] // Start, end, description
            ]),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks", $data);

        $response->assertStatus(200);
    }
}
