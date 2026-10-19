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

use App\Factory\CompanyUserFactory;
use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

class ActivityApiTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

    }

    public function testActivityInvoiceNotes()
    {
        $data = [
            'entity' => 'invoices',
            'entity_id' => $this->invoice->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityCreditNotes()
    {
        $data = [
            'entity' => 'credits',
            'entity_id' => $this->credit->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityQuoteNotes()
    {
        $data = [
            'entity' => 'quotes',
            'entity_id' => $this->quote->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }


    public function testActivityClientNotes()
    {
        $data = [
            'entity' => 'clients',
            'entity_id' => $this->client->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }


    public function testActivityRecurringInvoiceNotes()
    {
        $data = [
            'entity' => 'recurring_invoices',
            'entity_id' => $this->recurring_invoice->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }


    public function testActivityExpenseNotes()
    {
        $data = [
            'entity' => 'expenses',
            'entity_id' => $this->expense->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityRecurringExpenseNotes()
    {
        $data = [
            'entity' => 'recurring_expenses',
            'entity_id' => $this->recurring_expense->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
        $this->assertEquals($this->recurring_expense->hashed_id, $arr['data']['recurring_expense_id']);
        $this->assertEquals('', $arr['data']['expense_id']);
    }

    public function testActivityEntityRecurringExpense()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', [
                'entity' => 'recurring_expenses',
                'entity_id' => $this->recurring_expense->hashed_id,
                'notes' => 'Recurring expense note',
            ]);

        $response->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/entity', [
                'entity' => 'recurring_expense',
                'entity_id' => $this->recurring_expense->hashed_id,
            ]);

        $response->assertStatus(200);

        $notes = collect($response->json('data'))->where('activity_type_id', Activity::USER_NOTE);

        $this->assertCount(1, $notes);
        $this->assertEquals('Recurring expense note', $notes->first()['notes']);
    }


    public function testActivityVendorNotes()
    {
        $data = [
            'entity' => 'vendors',
            'entity_id' => $this->vendor->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityPurchaseOrderNotes()
    {
        $data = [
            'entity' => 'purchase_orders',
            'entity_id' => $this->purchase_order->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
        $this->assertEquals($this->purchase_order->hashed_id, $arr['data']['purchase_order_id']);
        $this->assertEquals('', $arr['data']['expense_id']);
    }

    public function testActivityTaskNotes()
    {
        $data = [
            'entity' => 'tasks',
            'entity_id' => $this->task->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityProjectNotes()
    {
        $data = [
            'entity' => 'projects',
            'entity_id' => $this->project->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityPaymentNotes()
    {
        $data = [
            'entity' => 'payments',
            'entity_id' => $this->payment->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityEntity()
    {

        $invoice = $this->company->invoices()->first();

        $invoice->service()->markSent()->markPaid()->markDeleted()->handleRestore()->save();

        $data = [
            'entity' => 'invoice',
            'entity_id' => $invoice->hashed_id
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/activities/entity', $data);

        $response->assertStatus(200);


    }

    public function testActivityEntityFiltersByActivityTypeId()
    {
        $this->createCompanyActivityNote();

        $activity = new Activity();
        $activity->account_id = $this->account->id;
        $activity->company_id = $this->company->id;
        $activity->user_id = $this->user->id;
        $activity->client_id = $this->invoice->client_id;
        $activity->invoice_id = $this->invoice->id;
        $activity->activity_type_id = Activity::UPDATE_INVOICE;
        $activity->ip = '127.0.0.1';
        $activity->notes = '';
        $activity->created_at = now()->addMinute();
        $activity->save();

        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

        // Unfiltered, the newer system row fills the window and the note falls out of it.
        $response = $this->withHeaders($headers)->postJson('/api/v1/activities/entity', [
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
            'rows' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(Activity::UPDATE_INVOICE, $response->json('data.0.activity_type_id'));

        $response = $this->withHeaders($headers)->postJson('/api/v1/activities/entity', [
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
            'activity_type_id' => (string) Activity::USER_NOTE,
            'rows' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(Activity::USER_NOTE, $response->json('data.0.activity_type_id'));
        $this->assertEquals('Seeded activity for feed tests', $response->json('data.0.notes'));

        $response = $this->withHeaders($headers)->postJson('/api/v1/activities/entity', [
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
            'activity_type_id' => Activity::USER_NOTE . ',' . Activity::UPDATE_INVOICE,
        ]);

        $response->assertStatus(200);

        $types = array_map('intval', array_column($response->json('data'), 'activity_type_id'));

        $this->assertContains(Activity::USER_NOTE, $types);
        $this->assertContains(Activity::UPDATE_INVOICE, $types);
        $this->assertEmpty(array_diff($types, [Activity::USER_NOTE, Activity::UPDATE_INVOICE]));
    }

    public function testActivityEntityRejectsAMalformedActivityTypeId()
    {
        $this->withExceptionHandling();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/activities/entity', [
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
            'activity_type_id' => 'note',
        ]);

        $response->assertStatus(422);
    }

    public function testActivityGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/activities/');

        $response->assertStatus(200);
    }

    public function testActivityGetWithReact()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/activities?react=true');

        $response->assertStatus(200);
    }

    public function testActivityGetWithReactV2()
    {
        $this->createCompanyActivityNote();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/activities?reactv2');

        $response->assertStatus(200);

        $activity = $response->json('data.0');

        $this->assertIsArray($activity);
        $this->assertArrayHasKey('activity_type_id', $activity);
        $this->assertArrayHasKey('hashed_id', $activity);
        $this->assertArrayHasKey('created_at', $activity);
    }

    public function testActivityFeedDoesNotNPlusOneQueries()
    {
        $this->createCompanyActivityNote();

        DB::enableQueryLog();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/activities?reactv2');

        $response->assertStatus(200);

        $this->assertLessThan(
            50,
            count(DB::getQueryLog()),
            'Activity feed should batch eager loads instead of per-row lazy loading.',
        );
    }

    public function testActivityEntityFeedReturnsStructuredPayload()
    {
        $invoice = $this->company->invoices()->first();

        $this->createCompanyActivityNote();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/activities/entity', [
            'entity' => 'invoice',
            'entity_id' => $invoice->hashed_id,
        ]);

        $response->assertStatus(200);

        $activities = $response->json('data');

        $this->assertIsArray($activities);
        $this->assertNotEmpty($activities);

        foreach ($activities as $activity) {
            $this->assertArrayHasKey('activity_type_id', $activity);
            $this->assertArrayHasKey('hashed_id', $activity);
            $this->assertArrayHasKey('created_at', $activity);
        }
    }

    public function testActivityListScopesAUserWithoutViewActivityToTheirOwnRows()
    {
        $this->createCompanyActivityNote();

        [$user, $token] = $this->restrictedUserToken();

        $own = $this->createActivityBy($user);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->get('/api/v1/activities');

        $response->assertStatus(200);
        $this->assertEquals([$own->hashed_id], array_column($response->json('data'), 'id'));
    }

    public function testActivityFeedFiltersByUserId()
    {
        $this->createCompanyActivityNote();

        [$user, $token] = $this->restrictedUserToken();

        $theirs = $this->createActivityBy($user);

        $admin = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

        $response = $this->withHeaders($admin)->get('/api/v1/activities?reactv2&user_id=' . $user->hashed_id);

        $response->assertStatus(200);
        $this->assertEquals([$theirs->id], array_column($response->json('data'), 'id'));

        $response = $this->withHeaders($admin)->get('/api/v1/activities?user_id=' . $user->hashed_id);

        $response->assertStatus(200);
        $this->assertEquals([$theirs->hashed_id], array_column($response->json('data'), 'id'));

        $response = $this->withHeaders($admin)->get('/api/v1/activities?reactv2&user_id=not-a-hashed-id');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));

        // Applied inside the non-admin clamp: asking for another user's rows can't widen it.
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->get('/api/v1/activities?reactv2&user_id=' . $this->user->hashed_id);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function testActivityFeedPagesWithABeforeCursor()
    {
        // Newer than anything the fixture seeds, oldest first. The last two share a timestamp,
        // so only the id can order them across a page boundary.
        $base = now()->addDay()->startOfMinute();
        $activities = [];

        foreach ([0, 1, 2, 2] as $offset) {
            $activities[] = $this->createActivityBy($this->user, $base->copy()->addSeconds($offset));
        }

        $ids = array_map(fn (Activity $activity) => $activity->id, $activities);

        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

        $response = $this->withHeaders($headers)->get('/api/v1/activities?reactv2&rows=3');

        $response->assertStatus(200);
        $this->assertEquals([$ids[3], $ids[2], $ids[1]], array_map('intval', array_column($response->json('data'), 'id')));

        // A cut inside the shared timestamp carries on with the other row of that timestamp.
        $response = $this->withHeaders($headers)->get('/api/v1/activities?reactv2&rows=2&before=' . $activities[3]->hashed_id);

        $response->assertStatus(200);
        $this->assertEquals([$ids[2], $ids[1]], array_map('intval', array_column($response->json('data'), 'id')));

        $response = $this->withHeaders($headers)->get('/api/v1/activities?reactv2&rows=2&before=' . $activities[2]->hashed_id);

        $response->assertStatus(200);
        $this->assertEquals([$ids[1], $ids[0]], array_map('intval', array_column($response->json('data'), 'id')));
    }

    public function testActivityFeedCursorIsExactWithinASecond()
    {
        // Same second, and the row written first carries the later timestamp: feed order follows
        // the stored microseconds, not the id, and so must the cursor.
        $second = now()->addDay()->startOfMinute();

        $later = $this->createActivityBy($this->user, $second->copy()->addMicroseconds(700000));
        $earlier = $this->createActivityBy($this->user, $second->copy()->addMicroseconds(200000));

        $this->assertGreaterThan($later->id, $earlier->id);

        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

        $response = $this->withHeaders($headers)->get('/api/v1/activities?reactv2&rows=2');

        $response->assertStatus(200);
        $this->assertEquals([$later->id, $earlier->id], array_map('intval', array_column($response->json('data'), 'id')));

        $response = $this->withHeaders($headers)->get('/api/v1/activities?reactv2&rows=1&before=' . $later->hashed_id);

        $response->assertStatus(200);
        $this->assertEquals([$earlier->id], array_map('intval', array_column($response->json('data'), 'id')));
    }

    public function testActivityFeedCursorKeepsANonAdminToTheirOwnRows()
    {
        [$user, $token] = $this->restrictedUserToken();

        $base = now()->addDay()->startOfMinute();

        $older = $this->createActivityBy($user, $base);
        $this->createActivityBy($this->user, $base->copy()->addSeconds(1));
        $newer = $this->createActivityBy($user, $base->copy()->addSeconds(2));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->get('/api/v1/activities?reactv2&before=' . $newer->hashed_id);

        $response->assertStatus(200);
        $this->assertEquals([$older->id], array_map('intval', array_column($response->json('data'), 'id')));
    }

    public function testActivityFeedCursorMustBeAnActivityOfTheCompany()
    {
        $this->withExceptionHandling();

        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $foreign = new Activity();
        $foreign->account_id = $this->account->id;
        $foreign->company_id = $other_company->id;
        $foreign->user_id = $this->user->id;
        $foreign->activity_type_id = Activity::CREATE_CLIENT;
        $foreign->ip = '127.0.0.1';
        $foreign->notes = '';
        $foreign->save();

        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

        foreach (['before=' . $foreign->hashed_id, 'before=not-a-hashed-id', 'before=', 'before[]=1'] as $query) {
            $response = $this->withHeaders($headers)->getJson('/api/v1/activities?reactv2&' . $query);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['before']);
        }
    }

    private function createActivityBy(User $user, ?\DateTimeInterface $created_at = null): Activity
    {
        $activity = new Activity();
        $activity->account_id = $this->account->id;
        $activity->company_id = $this->company->id;
        $activity->user_id = $user->id;
        $activity->client_id = $this->invoice->client_id;
        $activity->invoice_id = $this->invoice->id;
        $activity->activity_type_id = Activity::UPDATE_INVOICE;
        $activity->ip = '127.0.0.1';
        $activity->notes = '';

        if ($created_at) {
            $activity->created_at = $created_at;
        }

        $activity->save();

        return $activity;
    }

    /**
     * A user of this company who is neither owner nor admin and may only view invoices, with an API token.
     *
     * @return array{0: User, 1: string}
     */
    private function restrictedUserToken(): array
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('restricted') . '@gmail.com',
        ]);

        $company_user = CompanyUserFactory::create($user->id, $this->company->id, $this->account->id);
        $company_user->is_owner = false;
        $company_user->is_admin = false;
        $company_user->is_locked = false;
        $company_user->permissions = '["view_invoice"]';
        $company_user->save();

        $token = Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'restricted token';
        $company_token->token = $token;
        $company_token->is_system = true;
        $company_token->save();

        return [$user, $token];
    }

    private function createCompanyActivityNote(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/activities/notes', [
            'entity' => 'invoices',
            'entity_id' => $this->invoice->hashed_id,
            'notes' => 'Seeded activity for feed tests',
        ]);

        $response->assertStatus(200);
    }
}
