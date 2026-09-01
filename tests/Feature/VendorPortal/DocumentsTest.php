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
use App\Http\Requests\Document\DownloadMultipleDocumentsRequest;
use App\Models\Account;
use App\Models\Company;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Utils\Traits\AppSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use AppSetup;
    use DatabaseTransactions;

    private Account $account;

    private User $user;

    private Company $company;

    private Vendor $vendor;

    private VendorContact $contact;

    private Vendor $otherVendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        $this->company = Company::factory()->create(['account_id' => $this->account->id]);
        $this->company->settings = CompanySettings::defaults();
        $this->company->save();

        $this->vendor = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'currency_id' => 1,
        ]);
        $this->contact = VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $this->company->id,
        ]);

        $this->otherVendor = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'currency_id' => 1,
        ]);
        VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'vendor_id' => $this->otherVendor->id,
            'company_id' => $this->company->id,
        ]);

        $this->actingAs($this->contact, 'vendor');
        $this->withoutMiddleware();
    }

    public function testDownloadMultipleAllowsPublicVendorDocuments(): void
    {
        $document = $this->createDocumentFor($this->vendor);

        $this->assertTrue($this->authorize([$document]));
    }

    public function testDownloadMultipleBlocksOtherVendorDocuments(): void
    {
        $document = $this->createDocumentFor($this->otherVendor);

        $this->assertFalse($this->authorize([$document]));
    }

    public function testDownloadMultipleBlocksPrivatePurchaseOrderDocuments(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->vendor);
        $document = $this->createDocumentFor($purchaseOrder, false);

        $this->assertFalse($this->authorize([$document]));
    }

    public function testDownloadMultipleAllowsPublicPurchaseOrderDocuments(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->vendor);
        $document = $this->createDocumentFor($purchaseOrder);

        $this->assertTrue($this->authorize([$document]));
    }

    public function testDownloadMultipleBlocksOtherVendorPurchaseOrderDocuments(): void
    {
        $purchaseOrder = $this->createPurchaseOrder($this->otherVendor);
        $document = $this->createDocumentFor($purchaseOrder);

        $this->assertFalse($this->authorize([$document]));
    }

    public function testDownloadMultipleBlocksMixedAuthorizedAndUnauthorizedDocuments(): void
    {
        $ownDocument = $this->createDocumentFor($this->vendor);
        $otherDocument = $this->createDocumentFor($this->otherVendor);

        $this->assertFalse($this->authorize([$ownDocument, $otherDocument]));
    }

    public function testDownloadMultipleAllowsPublicCompanyDocuments(): void
    {
        $document = $this->createDocumentFor($this->company);

        $this->assertTrue($this->authorize([$document]));
    }

    public function testDownloadMultipleBlocksPrivateCompanyDocuments(): void
    {
        $document = $this->createDocumentFor($this->company, false);

        $this->assertFalse($this->authorize([$document]));
    }

    public function testDownloadMultipleBlocksDocumentsFromAnotherCompany(): void
    {
        $otherCompany = Company::factory()->create(['account_id' => $this->account->id]);
        $document = Document::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);
        $otherCompany->documents()->save($document);

        $this->assertFalse($this->authorize([$document]));
    }

    public function testDownloadMultipleEndpointRejectsAnUnauthorizedDocument(): void
    {
        $document = $this->createDocumentFor($this->otherVendor);

        $response = $this->postJson(route('vendor.documents.download_multiple'), [
            'file_hash' => [$document->hashed_id],
        ]);

        $response->assertUnauthorized();
    }

    /**
     * @param array<int, Document> $documents
     */
    private function authorize(array $documents): bool
    {
        $request = DownloadMultipleDocumentsRequest::create(
            route('vendor.documents.download_multiple'),
            'POST',
            ['file_hash' => collect($documents)->pluck('hashed_id')->all()],
        );

        return $request->authorize();
    }

    private function createDocumentFor(Model $documentable, bool $isPublic = true): Document
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'is_public' => $isPublic,
        ]);

        $documentable->documents()->save($document);

        return $document->refresh();
    }

    private function createPurchaseOrder(Vendor $vendor): PurchaseOrder
    {
        return PurchaseOrder::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'vendor_id' => $vendor->id,
        ]);
    }
}
