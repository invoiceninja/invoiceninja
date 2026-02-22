<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\DataMapper\InvoiceSync;

class InvoiceSyncCastTest extends TestCase
{
    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();
    }
    public function test_it_can_cast_from_array()
    {

        /** @var Account $a */
        $a = Account::factory()->create();
        /** @var Company $c */
        $c = Company::factory()->create(['account_id' => $a->id]);
        /** @var User $u */
        $u = User::factory()->create(['account_id' => $a->id, 'email' => $this->faker->safeEmail()]);
        /** @var Client $cl */
        $cl = Client::factory()->create(['company_id' => $c->id]);
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create(['company_id' => $c->id, 'client_id' => $cl->id, 'user_id' => $u->id]);

        $this->assertNotNull($invoice);
        $this->assertNull($invoice->sync);

        $sync = new InvoiceSync(qb_id: '123', dn_completed: false);
        $sync->addInvitation('test_invitation', 'DN123', 'DN_INV_123', 'test_sig');
        $invoice->sync = $sync;
        $invoice->save();

        $this->assertEquals('123', $invoice->sync->qb_id);
        $this->assertFalse($invoice->sync->dn_completed);
        $this->assertCount(1, $invoice->sync->invitations);
        
        $invitation = $invoice->sync->getInvitation('test_invitation');
        $this->assertNotNull($invitation);
        $this->assertEquals('DN123', $invitation['dn_id']);
        $this->assertEquals('DN_INV_123', $invitation['dn_invitation_id']);
        $this->assertEquals('test_sig', $invitation['dn_sig']);

        $a->delete();
    }

    public function test_it_can_query_json_with_cast()
    {
        // This test demonstrates that JSON queries work with cast columns
        // The cast doesn't interfere with database-level JSON queries

        /** @var Account $a */
        $a = Account::factory()->create();
        /** @var Company $c */
        $c = Company::factory()->create(['account_id' => $a->id]);
        /** @var User $u */
        $u = User::factory()->create(['account_id' => $a->id, 'email' => $this->faker->safeEmail()]);
        /** @var Client $cl */
        $cl = Client::factory()->create(['company_id' => $c->id]);
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create(['company_id' => $c->id, 'client_id' => $cl->id, 'user_id' => $u->id]);

        // Set sync data using the cast with new invitation structure
        $sync = new InvoiceSync(qb_id: 'QB123', dn_completed: true);
        $sync->addInvitation('invitation_1', 'DN456', 'DN_INV_001', 'signature_001');
        $sync->addInvitation('invitation_2', 'DN789', 'DN_INV_002', 'signature_002');
        $invoice->sync = $sync;
        $invoice->save();

        // The key demonstration: JSON queries work on cast columns
        // This proves that casts don't interfere with database JSON operations

        // Method 1: whereJsonContains for top-level properties
        $found = Invoice::whereJsonContains('sync->qb_id', 'QB123')->first();
        $this->assertNotNull($found);
        $this->assertEquals('QB123', $found->sync->qb_id);
        $this->assertTrue($found->sync->dn_completed);

        // Method 2: JSON path queries for top-level properties
        $found = Invoice::where('sync->qb_id', 'QB123')->first();
        $this->assertNotNull($found);
        $this->assertTrue($found->sync->dn_completed);

        // Method 3: JSON queries for invitation data
        $found = Invoice::whereJsonContains('sync->invitations', ['invitation_key' => 'invitation_1'])->first();
        $this->assertNotNull($found);
        
        // Verify invitation data can be retrieved
        $invitation1 = $found->sync->getInvitation('invitation_1');
        $this->assertNotNull($invitation1);
        $this->assertEquals('DN456', $invitation1['dn_id']);
        $this->assertEquals('DN_INV_001', $invitation1['dn_invitation_id']);
        $this->assertEquals('signature_001', $invitation1['dn_sig']);

        // Method 4: JSON queries with different operators
        $found = Invoice::whereJsonLength('sync->qb_id', '>', 0)->first();
        $this->assertNotNull($found);

        // This proves that all JSON query methods work with cast columns
        $a->delete();
    }
}
