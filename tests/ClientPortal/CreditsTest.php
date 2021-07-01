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

namespace Tests\ClientPortal;


use App\Http\Livewire\CreditsTable;
use App\Models\Credit;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\MockAccountData;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    public function testShowingOnlyQuotesWithDueDateLessOrEqualToNow()
    {
        Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'testing-number-01',
            'due_date' => now()->subDays(5),
            'status_id' => Credit::STATUS_SENT,
        ]);

        Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'testing-number-02',
            'due_date' => now(),
            'status_id' => Credit::STATUS_SENT,
        ]);

        Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'testing-number-03',
            'due_date' => now()->addDays(5),
            'status_id' => Credit::STATUS_SENT,
        ]);

        $this->actingAs($this->client->contacts->first(), 'contact');

        Livewire::test(CreditsTable::class, ['company' => $this->company])
            ->assertSee('testing-number-01')
            ->assertSee('testing-number-02')
            ->assertDontSee('testing-number-03');
    }
}
