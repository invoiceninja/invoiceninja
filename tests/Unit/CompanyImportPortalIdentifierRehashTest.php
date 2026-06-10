<?php

namespace Tests\Unit;

use App\Jobs\Company\CompanyImport;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Models\User;
use App\Models\VendorContact;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class CompanyImportPortalIdentifierRehashTest extends TestCase
{
    public function test_it_rehashes_imported_portal_identifiers_when_company_keys_differ(): void
    {
        $job = $this->makeJob(rehashPortalIdentifiers: true);

        $client = $this->rehashImportedPortalIdentifiers($job, Client::class, [
            'client_hash' => 'source-client-hash',
        ]);

        $client_contact = $this->rehashImportedPortalIdentifiers($job, ClientContact::class, [
            'contact_key' => 'source-contact-key',
        ]);

        $vendor_contact = $this->rehashImportedPortalIdentifiers($job, VendorContact::class, [
            'contact_key' => 'source-vendor-contact-key',
        ]);

        $this->assertNotSame('source-client-hash', $client['client_hash']);
        $this->assertSame(40, strlen($client['client_hash']));

        $this->assertNotSame('source-contact-key', $client_contact['contact_key']);
        $this->assertSame(32, strlen($client_contact['contact_key']));

        $this->assertNotSame('source-vendor-contact-key', $vendor_contact['contact_key']);
        $this->assertSame(32, strlen($vendor_contact['contact_key']));
    }

    public function test_it_rehashes_imported_invitation_keys_when_company_keys_differ(): void
    {
        config(['ninja.db.multi_db_enabled' => false]);

        $job = $this->makeJob(rehashPortalIdentifiers: true);

        foreach ($this->invitationClasses() as $class) {
            $invitation = $this->rehashImportedPortalIdentifiers($job, $class, [
                'key' => 'source-invitation-key',
            ]);

            $this->assertNotSame('source-invitation-key', $invitation['key']);
            $this->assertSame((int) config('ninja.key_length'), strlen($invitation['key']));
        }
    }

    public function test_it_rehashes_imported_invitation_keys_with_database_prefix_when_multi_db_is_enabled(): void
    {
        config(['ninja.db.multi_db_enabled' => true]);

        $job = $this->makeJob(
            rehashPortalIdentifiers: true,
            companyDb: MultiDB::DB_PREFIX . '01',
        );

        foreach ($this->invitationClasses() as $class) {
            $invitation = $this->rehashImportedPortalIdentifiers($job, $class, [
                'key' => 'source-invitation-key',
            ]);

            [$db_prefix, $key] = explode('-', $invitation['key'], 2);

            $this->assertSame($job->getDbCode(MultiDB::DB_PREFIX . '01'), $db_prefix);
            $this->assertSame((int) config('ninja.key_length'), strlen($key));
        }
    }

    public function test_it_preserves_imported_portal_identifiers_when_company_keys_match(): void
    {
        $job = $this->makeJob(rehashPortalIdentifiers: false);

        $client = $this->rehashImportedPortalIdentifiers($job, Client::class, [
            'client_hash' => 'source-client-hash',
        ]);

        $client_contact = $this->rehashImportedPortalIdentifiers($job, ClientContact::class, [
            'contact_key' => 'source-contact-key',
        ]);

        $vendor_contact = $this->rehashImportedPortalIdentifiers($job, VendorContact::class, [
            'contact_key' => 'source-vendor-contact-key',
        ]);

        $this->assertSame('source-client-hash', $client['client_hash']);
        $this->assertSame('source-contact-key', $client_contact['contact_key']);
        $this->assertSame('source-vendor-contact-key', $vendor_contact['contact_key']);
    }

    public function test_it_preserves_imported_invitation_keys_when_company_keys_match(): void
    {
        $job = $this->makeJob(rehashPortalIdentifiers: false);

        foreach ($this->invitationClasses() as $class) {
            $invitation = $this->rehashImportedPortalIdentifiers($job, $class, [
                'key' => 'source-invitation-key',
            ]);

            $this->assertSame('source-invitation-key', $invitation['key']);
        }
    }

    public function test_it_detects_when_imported_company_key_differs_from_target_company_key(): void
    {
        $job = $this->makeJob(rehashPortalIdentifiers: false, companyKey: 'target-company-key');
        $method = new ReflectionMethod($job, 'shouldRehashImportedPortalIdentifiers');

        $this->assertFalse($method->invoke($job, 'target-company-key'));
        $this->assertTrue($method->invoke($job, 'source-company-key'));
    }

    private function makeJob(
        bool $rehashPortalIdentifiers,
        string $companyKey = 'target-company-key',
        string $companyDb = 'db-ninja-01',
    ): CompanyImport
    {
        $company = new Company();
        $company->company_key = $companyKey;
        $company->db = $companyDb;

        $job = new CompanyImport($company, new User(), '', []);

        $property = new ReflectionProperty($job, 'rehash_imported_portal_identifiers');
        $property->setValue($job, $rehashPortalIdentifiers);

        return $job;
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, string>
     */
    private function rehashImportedPortalIdentifiers(CompanyImport $job, string $class, array $payload): array
    {
        $method = new ReflectionMethod($job, 'rehashImportedPortalIdentifiers');

        return $method->invoke($job, $class, $payload);
    }

    /**
     * @return string[]
     */
    private function invitationClasses(): array
    {
        return [
            CreditInvitation::class,
            InvoiceInvitation::class,
            PurchaseOrderInvitation::class,
            QuoteInvitation::class,
            RecurringInvoiceInvitation::class,
        ];
    }
}
