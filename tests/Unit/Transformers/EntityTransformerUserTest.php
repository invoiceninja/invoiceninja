<?php

namespace Tests\Unit\Transformers;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Transformers\DocumentTransformer;
use App\Transformers\EntityTransformer;
use App\Transformers\InvoiceTransformer;
use App\Transformers\PaymentableTransformer;
use App\Transformers\PaymentTransformer;
use App\Utils\TruthSource;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ReflectionProperty;

class EntityTransformerUserTest extends TestCase
{
    #[DataProvider('serializers')]
    public function testNestedIncludesRetainUserAndSerializer(?string $serializer): void
    {
        $user = $this->adminUser();
        $client = new Client();
        $client->company_id = $user->company()->id;
        $client->setRelation('documents', new Collection());
        $invoice = new Invoice();
        $invoice->setRelation('client', $client);
        $invoice->setRelation('payments', new Collection());
        $transformer = new InvoiceTransformer($serializer, $user);

        $client_transformer = $transformer->includeClient($invoice)->getTransformer();
        $document_transformer = $client_transformer->includeDocuments($client)->getTransformer();
        $payment_transformer = $transformer->includePayments($invoice)->getTransformer();
        $payment = new Payment();
        $payment->setRelation('paymentables', new Collection());
        $paymentable_transformer = $payment_transformer->includePaymentables($payment)->getTransformer();

        foreach ([$client_transformer, $document_transformer, $payment_transformer, $paymentable_transformer] as $child) {
            $this->assertSame($user, (new ReflectionProperty(EntityTransformer::class, 'user'))->getValue($child));
            $this->assertSame($serializer, (new ReflectionProperty(EntityTransformer::class, 'serializer'))->getValue($child));
        }

        $type_transformer = $payment_transformer->includeType($payment)->getTransformer();
        $this->assertSame($user, (new ReflectionProperty(EntityTransformer::class, 'user'))->getValue($type_transformer));
    }

    public function testSerializerOnlyConstructionRemainsSupported(): void
    {
        foreach ([EntityTransformer::class, DocumentTransformer::class, PaymentTransformer::class, PaymentableTransformer::class] as $transformer_class) {
            $transformer = new $transformer_class('array');
            $this->assertNull((new ReflectionProperty(EntityTransformer::class, 'user'))->getValue($transformer));
            $this->assertSame('array', (new ReflectionProperty(EntityTransformer::class, 'serializer'))->getValue($transformer));
        }
    }

    public static function serializers(): array
    {
        return [[null], ['array'], ['json']];
    }

    private function adminUser(): User
    {
        $company = new Company();
        $company->id = 1;

        $company_user = new CompanyUser();
        $company_user->is_admin = true;
        $company_user->company_id = $company->id;

        $company_token = new CompanyToken();
        $company_token->setRelation('cu', $company_user);

        $user = new User();
        $user->setCompany($company);

        app(TruthSource::class)
            ->setCompany($company)
            ->setCompanyUser($company_user)
            ->setUser($user)
            ->setCompanyToken($company_token);

        return $user;
    }
}
