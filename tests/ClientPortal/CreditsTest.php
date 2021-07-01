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
        // Create two credits, one with due_date in future, one with now, one with less than now.
        Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'testing-number-01',
            'due_date' => now()->subDays(5),
        ]);

        Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'testing-number-02',
            'due_date' => now(),
        ]);

        Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'testing-number-03',
            'due_date' => now()->addDays(5),
        ]);

        $this->actingAs($this->client);

        //   Argument 1 passed to Illuminate\Foundation\Testing\TestCase::actingAs() must implement interface
        // Illuminate\Contracts\Auth\Authenticatable, instance of App\Models\Client given,
        // called in /var/www/html/tests/ClientPortal/CreditsTest.php on line 65

        Livewire::test(CreditsTable::class)
            ->assertSee('testing-number-01')
            ->assertSee('testing-number-02')
            ->assertDontSee('testing-number-03');
    }
}
