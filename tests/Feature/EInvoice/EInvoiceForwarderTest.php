<?php

namespace Tests\Feature\EInvoice;

use App\Models\Company;
use App\Models\Expense;
use Tests\TestCase;
use App\DataMapper\CompanySettings;
use App\Services\Email\Email;
use Illuminate\Support\Facades\Bus;
use Modules\Admin\Jobs\Storecove\ReceiveDocument;
use App\Services\EDocument\Gateway\Storecove\EInvoiceForwarder;

class EInvoiceForwarderTest extends TestCase
{
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
    }

    public function test_expense_forwarder_uses_expense_email_without_invoice_fallback(): void
    {
        $this->setForwardingEmails('invoice-forward@example.test', 'expense-forward@example.test');

        Bus::fake();

        $forwarder = EInvoiceForwarder::forExpenses($this->company);

        $this->assertTrue($forwarder->isConfigured());
        $this->assertSame('expense-forward@example.test', $forwarder->getForwardEmail());

        $forwarder->forward('<Invoice/>', 'received-guid.xml', 'received');

        Bus::assertDispatched(Email::class, function (Email $job) {
            return $this->hasRecipient($job, 'expense-forward@example.test')
                && $this->hasRecipientName($job, 'expense-forward@example.test', 'EInvoice Forwarding')
                && $job->email_object->subject === 'Peppol Document (received): received-guid.xml'
                && $job->email_object->attachments[0]['name'] === 'received-guid.xml'
                && base64_decode($job->email_object->attachments[0]['file']) === '<Invoice/>';
        });

        Bus::assertNotDispatched(Email::class, function (Email $job) {
            return $this->hasRecipient($job, 'invoice-forward@example.test');
        });
    }

    public function test_expense_forwarder_is_not_configured_without_valid_expense_email(): void
    {
        $this->setForwardingEmails('invoice-forward@example.test', '');

        $forwarder = EInvoiceForwarder::forExpenses($this->company);

        $this->assertFalse($forwarder->isConfigured());
        $this->assertSame('', $forwarder->getForwardEmail());

        $this->setForwardingEmails('invoice-forward@example.test', 'not-an-email');

        $forwarder = EInvoiceForwarder::forExpenses($this->company);

        $this->assertFalse($forwarder->isConfigured());
        $this->assertSame('not-an-email', $forwarder->getForwardEmail());
    }

    public function test_hosted_receive_document_forwards_original_xml_to_expense_email(): void
    {
        $this->setForwardingEmails('invoice-forward@example.test', 'expense-forward@example.test');

        $job = $this->makeReceiveDocumentJob();

        Bus::fake();

        $this->invokePrivateMethod($job, 'store');

        Bus::assertDispatched(Email::class, function (Email $job) {
            return $this->hasRecipient($job, 'expense-forward@example.test')
                && $this->hasRecipientName($job, 'expense-forward@example.test', 'EInvoice Forwarding')
                && $job->email_object->subject === 'Peppol Document (received): SUP-123.xml'
                && $job->email_object->attachments[0]['name'] === 'SUP-123.xml'
                && base64_decode($job->email_object->attachments[0]['file']) === '<Invoice>hosted</Invoice>';
        });

        Bus::assertNotDispatched(Email::class, function (Email $job) {
            return $this->hasRecipient($job, 'invoice-forward@example.test')
                && str_starts_with($job->email_object->subject ?? '', 'Peppol Document');
        });
    }

    public function test_hosted_receive_document_does_not_forward_without_valid_expense_email(): void
    {
        foreach (['', 'not-an-email'] as $expense_email) {
            $this->setForwardingEmails('invoice-forward@example.test', $expense_email);

            Bus::fake();

            $this->invokePrivateMethod($this->makeReceiveDocumentJob(), 'store');

            Bus::assertNotDispatched(Email::class, function (Email $job) {
                return str_starts_with($job->email_object->subject ?? '', 'Peppol Document');
            });
        }
    }

    private function setForwardingEmails(string $invoice_email, string $expense_email): void
    {
        $settings = $this->company->settings;
        $settings->e_invoice_forward_email = $invoice_email;
        $settings->e_expense_forward_email = $expense_email;

        $this->company->settings = $settings;
    }

    private function makeCompany(): Company
    {
        $settings = CompanySettings::defaults();
        $settings->language_id = '';

        $company = new Company();
        $company->company_key = 'company-key';
        $company->db = config('database.default');
        $company->settings = $settings;
        $company->setRelation('company_users', collect());

        return $company;
    }

    private function makeReceiveDocumentJob(): ReceiveDocument
    {

        if(!class_exists(ReceiveDocument::class)){
            $this->markTestSkipped('ReceiveDocument class does not exist');
        }
        
        $expense = new Expense();
        $expense->private_notes = 'SUP-123';

        $job = new ReceiveDocument([
            'event_group' => 'invoice',
            'tenant_id' => $this->company->company_key,
            'document_guid' => 'received-document-guid',
        ]);

        $this->setPrivateProperty($job, 'hosted_platform', true);
        $this->setPrivateProperty($job, 'company', $this->company);
        $this->setPrivateProperty($job, 'expense', $expense);
        $this->setPrivateProperty($job, 'original_base64_xml', base64_encode('<Invoice>hosted</Invoice>'));

        return $job;
    }

    private function hasRecipient(Email $job, string $email): bool
    {
        foreach ($job->email_object->to as $address) {
            if ($address->address === $email) {
                return true;
            }
        }

        return false;
    }

    private function hasRecipientName(Email $job, string $email, string $name): bool
    {
        foreach ($job->email_object->to as $address) {
            if ($address->address === $email && $address->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setValue($object, $value);
    }

    private function invokePrivateMethod(object $object, string $method): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);

        return $method->invoke($object);
    }
}
