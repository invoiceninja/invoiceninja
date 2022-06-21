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

namespace Tests\Unit;

use App\Factory\InvoiceInvitationFactory;
use App\Models\CompanyToken;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use MakesHash;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();
    }

    public function testInvitationSanity()
    {
        $this->assertEquals($this->invoice->invitations->count(), 2);

        $invitations = $this->invoice->invitations()->get();

        $invites = $invitations->reject(function ($invitation) {
            return $invitation->contact->is_primary == false;
        })->toArray();

        $this->assertEquals(1, count($invites));

        $this->invoice->invitations = $invites;

        $this->invoice->line_items = [];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), $this->invoice->toArray());
        } catch (ValidationException $e) {
            nlog($e->getMessage());
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(2, count($arr['data']['invitations']));

        //test pushing a contact invitation back on

        $contact = $this->invoice->client->contacts->where('is_primary', false)->first();

        $new_invite = InvoiceInvitationFactory::create($this->invoice->company_id, $this->invoice->user_id);
        $new_invite->client_contact_id = $contact->hashed_id;
        $new_invite->key = $this->createDbHash(config('database.default'));

        $invitations = $this->invoice->invitations()->get();

        $invitations->push($new_invite);

        $this->invoice->invitations = $invitations->toArray();
        $this->invoice->line_items = [];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), $this->invoice->toArray())
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(2, count($arr['data']['invitations']));
    }
}
