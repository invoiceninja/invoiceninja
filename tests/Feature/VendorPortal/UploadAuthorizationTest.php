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

namespace Tests\Feature\VendorPortal;

use App\DataMapper\CompanySettings;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\SessionDomains;
use App\Http\Middleware\SetDomainNameDb;
use App\Http\Middleware\VendorLocale;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Account;
use App\Models\Company;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Utils\Traits\AppSetup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class UploadAuthorizationTest extends TestCase
{
    use AppSetup;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            SessionDomains::class,
            SetDomainNameDb::class,
            VendorLocale::class,
            VerifyCsrfToken::class,
        ]);
    }

    public function test_vendor_cannot_upload_document_to_another_vendors_purchase_order(): void
    {
        [, $user, $company, , $contact] = $this->createVendorContext();
        $otherVendor = Vendor::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'currency_id' => 1,
        ]);
        $otherPurchaseOrder = PurchaseOrder::factory()->create([
            'company_id' => $company->id,
            'vendor_id' => $otherVendor->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($contact, 'vendor')->postJson(
            route('vendor.upload.store', ['purchase_order' => $otherPurchaseOrder->hashed_id]),
            ['file' => UploadedFile::fake()->create('vendor-proof.pdf', 10, 'application/pdf')],
        );

        $this->assertSame([
            'response_status' => 401,
            'document_created' => false,
        ], [
            'response_status' => $response->status(),
            'document_created' => Document::whereMorphedTo('documentable', $otherPurchaseOrder)->exists(),
        ]);
    }

    public function test_vendor_cannot_upload_document_to_purchase_order_in_another_company(): void
    {
        [, , , , $contact] = $this->createVendorContext();
        [, $otherUser, $otherCompany, $otherVendor] = $this->createVendorContext();
        $otherPurchaseOrder = PurchaseOrder::factory()->create([
            'company_id' => $otherCompany->id,
            'vendor_id' => $otherVendor->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($contact, 'vendor')->postJson(
            route('vendor.upload.store', ['purchase_order' => $otherPurchaseOrder->hashed_id]),
            ['file' => UploadedFile::fake()->create('vendor-proof.pdf', 10, 'application/pdf')],
        );

        $this->assertSame([
            'response_status' => 401,
            'document_created' => false,
        ], [
            'response_status' => $response->status(),
            'document_created' => Document::whereMorphedTo('documentable', $otherPurchaseOrder)->exists(),
        ]);
    }

    /**
     * @return array{0: Account, 1: User, 2: Company, 3: Vendor, 4: VendorContact}
     */
    private function createVendorContext(): array
    {
        $account = Account::factory()->create([
            'plan' => 'pro',
            'plan_expires' => now()->addMonth(),
        ]);
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => Str::uuid() . '@example.test',
        ]);
        $company = Company::factory()->create(['account_id' => $account->id]);
        $settings = CompanySettings::defaults();
        $settings->vendor_portal_enable_uploads = true;
        $company->settings = $settings;
        $company->save();
        $vendor = Vendor::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'currency_id' => 1,
        ]);
        $contact = VendorContact::factory()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'user_id' => $user->id,
        ]);

        return [$account, $user, $company, $vendor, $contact];
    }
}
