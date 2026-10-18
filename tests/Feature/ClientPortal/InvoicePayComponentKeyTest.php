<?php

namespace Tests\Feature\ClientPortal;

use App\Livewire\Flow2\InvoicePay;
use App\Livewire\Flow2\PaymentMethod;
use App\Livewire\Flow2\ProcessPayment;
use Tests\TestCase;

class InvoicePayComponentKeyTest extends TestCase
{
    private function makeReadyPaymentComponent(): InvoicePay
    {
        $component = new InvoicePay();
        $component->invitation_id = 123;
        $component->signing_invitation_id = null;
        $component->signing_key = null;
        $component->terms_accepted = true;
        $component->signature_accepted = true;
        $component->under_over_payment = false;
        $component->payment_method_accepted = true;
        $component->required_fields = false;
        $component->payment_attempt_key = 'gateway-1:1:100:invoice-set';

        return $component;
    }

    public function testMainComponentKeyIsStableForSamePaymentAttempt(): void
    {
        $component = $this->makeReadyPaymentComponent();

        $this->assertSame(ProcessPayment::class, $component->component());

        $first_key = $component->componentUniqueId('main');
        usleep(1000);
        $second_key = $component->componentUniqueId('main');

        $this->assertSame($first_key, $second_key);
    }

    public function testMainComponentKeyDoesNotCreateMultipleRemountKeysForSamePaymentAttempt(): void
    {
        $component = $this->makeReadyPaymentComponent();

        $keys = [];

        for ($index = 0; $index < 5; $index++) {
            $keys[] = $component->componentUniqueId('main');
            usleep(1000);
        }

        $this->assertCount(1, array_unique($keys));
    }

    public function testMainComponentKeyChangesForNewPaymentSelection(): void
    {
        $component = $this->makeReadyPaymentComponent();

        $first_key = $component->componentUniqueId('main');

        $component->payment_attempt_key = 'gateway-2:2:100:invoice-set';
        $second_key = $component->componentUniqueId('main');

        $this->assertNotSame($first_key, $second_key);
    }

    public function testDefaultComponentKeyCallsAreNotSafeForSiblingChildren(): void
    {
        $component = $this->makeReadyPaymentComponent();

        $summary_key_as_called_by_current_blade = $component->componentUniqueId('invoice-summary');
        $main_key_as_called_by_current_blade = $component->componentUniqueId(ProcessPayment::class);

        $this->assertNotSame(
            $summary_key_as_called_by_current_blade,
            $main_key_as_called_by_current_blade,
            'sibling children must not call componentUniqueId() without a slot discriminator'
        );
    }

    public function testSummaryAndMainComponentKeysAreNamespacedSeparately(): void
    {
        $component = $this->makeReadyPaymentComponent();

        $this->assertNotSame(
            $component->componentUniqueId('summary'),
            $component->componentUniqueId('main')
        );
    }

    public function testPaymentAttemptKeyDoesNotChangeNonPaymentStepKey(): void
    {
        $component = $this->makeReadyPaymentComponent();
        $component->payment_method_accepted = false;

        $this->assertSame(PaymentMethod::class, $component->component());

        $first_key = $component->componentUniqueId('main');

        $component->payment_attempt_key = 'gateway-2:2:100:invoice-set';
        $second_key = $component->componentUniqueId('main');

        $this->assertSame($first_key, $second_key);
    }
}
