<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\BillingPortal;

use App\Livewire\BillingPortal\Authentication\RegisterOrLogin;
use App\Livewire\BillingPortal\Cart\Cart;
use App\Livewire\BillingPortal\Payments\Methods;
use App\Livewire\BillingPortal\Purchase;
use App\Livewire\BillingPortal\RFF;
use App\Livewire\BillingPortal\Setup;
use App\Livewire\BillingPortal\Submit;
use App\Services\Subscription\StepService;
use Tests\TestCase;

class DependencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('No Bueno');

    }

    public function testDependencyOrder()
    {
        $results = StepService::check([
            RegisterOrLogin::class,
            Cart::class,
        ]);

        $this->assertCount(1, $results);

        $results = StepService::check([
            RegisterOrLogin::class,
            Cart::class,
        ]);

        $this->assertCount(0, $results);

        $results = StepService::check([
            RegisterOrLogin::class,
            Cart::class,
        ]);

        $this->assertCount(0, $results);
    }

    public function testSorting()
    {
        $results = $this->sort([
            Methods::class,
            RegisterOrLogin::class,
            Cart::class,
        ]);

        $this->assertEquals(Purchase::defaultSteps(), $results);

        $results = $this->sort([
            RegisterOrLogin::class,
            Methods::class,
            Cart::class,
        ]);

        $this->assertEquals([
            Setup::class,
            RegisterOrLogin::class,
            Methods::class,
            Cart::class,
            Submit::class,
        ], $results);

        $results = $this->sort([
            RegisterOrLogin::class,
            Cart::class,
        ]);

        $this->assertEquals([
            Setup::class,
            RegisterOrLogin::class,
            Cart::class,
            Submit::class,
        ], $results);
    }

    private function sort(array $dependencies): array
    {
        $errors = StepService::check($dependencies);

        if (count($errors)) {
            return Purchase::defaultSteps();
        }

        return [Setup::class, ...$dependencies, Submit::class]; // Note: Re-index if you're doing any index-based checking/comparision.
    }
}
