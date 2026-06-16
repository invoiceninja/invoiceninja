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

use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ActivityRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private ActivityRepository $activityRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->activityRepository = new ActivityRepository();
    }

    public function testGetObjectVarsExtractsSameKeyValuePairsAsStdClassForeach(): void
    {
        $fields = new \stdClass();
        $fields->user_id = 1;
        $fields->company_id = 2;
        $fields->client_id = 3;
        $fields->invoice_id = 4;
        $fields->activity_type_id = Activity::CREATE_INVOICE;

        $from_foreach = [];

        foreach ($fields as $key => $value) {
            $from_foreach[$key] = $value;
        }

        $this->assertSame($from_foreach, get_object_vars($fields));
    }

    public function testSavePersistsStdClassFieldsOnActivity(): void
    {
        $fields = new \stdClass();
        $fields->user_id = $this->invoice->user_id;
        $fields->invoice_id = $this->invoice->id;
        $fields->client_id = $this->invoice->client_id;
        $fields->company_id = $this->invoice->company_id;
        $fields->activity_type_id = Activity::EMAIL_INVOICE;
        $fields->recurring_invoice_id = $this->invoice->recurring_id;

        $event_vars = [
            'ip' => '10.0.0.1',
            'token' => null,
            'is_system' => false,
            'user_id' => $this->user->id,
        ];

        $this->activityRepository->save($fields, $this->invoice, $event_vars);

        $activity = Activity::query()
            ->where('invoice_id', $this->invoice->id)
            ->where('activity_type_id', Activity::EMAIL_INVOICE)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($fields->user_id, $activity->user_id);
        $this->assertEquals($fields->invoice_id, $activity->invoice_id);
        $this->assertEquals($fields->client_id, $activity->client_id);
        $this->assertEquals($fields->company_id, $activity->company_id);
        $this->assertEquals($fields->activity_type_id, $activity->activity_type_id);
        $this->assertEquals($fields->recurring_invoice_id, $activity->recurring_invoice_id);
        $this->assertEquals($this->company->account_id, $activity->account_id);
        $this->assertEquals('10.0.0.1', $activity->ip);
        $this->assertFalse($activity->is_system);
    }

    public function testSaveAssignsEveryPropertyReturnedByGetObjectVars(): void
    {
        $fields = new \stdClass();
        $fields->user_id = $this->user->id;
        $fields->company_id = $this->company->id;
        $fields->client_id = $this->client->id;
        $fields->invoice_id = $this->invoice->id;
        $fields->activity_type_id = Activity::EMAIL_INVOICE;
        $fields->notes = 'test activity note';

        $event_vars = [
            'ip' => '192.168.1.1',
            'token' => null,
            'is_system' => true,
            'user_id' => $this->user->id,
        ];

        $this->activityRepository->save($fields, $this->invoice, $event_vars);

        $activity = Activity::query()->latest('id')->first();

        $this->assertNotNull($activity);

        foreach (get_object_vars($fields) as $key => $value) {
            $this->assertEquals(
                $value,
                $activity->{$key},
                "Failed asserting property [{$key}] was assigned from stdClass fields."
            );
        }
    }
}
