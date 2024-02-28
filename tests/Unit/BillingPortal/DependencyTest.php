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
use Tests\TestCase;

class DependencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDependencyOrder()
    {
        $results = $this->checkDependencies([
            RFF::class,
            RegisterOrLogin::class,
            Cart::class,
        ]);

        $this->assertCount(1, $results);

        $results = $this->checkDependencies([
            RegisterOrLogin::class,
            Cart::class,
            RFF::class,
        ]);

        $this->assertCount(0, $results);

        $results = $this->checkDependencies([
            RegisterOrLogin::class,
            RFF::class,
            Cart::class,
        ]);

        $this->assertCount(0, $results);
    }

    public function testSorting()
    {
        $results = $this->sort([
            RFF::class,
            Methods::class,
            RegisterOrLogin::class,
            Cart::class,
        ]);
        
        $this->assertEquals(Purchase::$steps, $results);
        
        $results = $this->sort([
            RegisterOrLogin::class,
            RFF::class,
            Methods::class,
            Cart::class,
        ]);
        
        $this->assertEquals([
            Setup::class,
            RegisterOrLogin::class,
            RFF::class,
            Methods::class,
            Cart::class,
            Submit::class,
        ], $results);

        $results = $this->sort([
            RegisterOrLogin::class,
            RFF::class,
            Cart::class,
        ]);

        $this->assertEquals([
            Setup::class,
            RegisterOrLogin::class,
            RFF::class,
            Cart::class,
            Submit::class,
        ], $results);
    }

    private function checkDependencies(array $steps): array
    {
        $dependencies = Purchase::$dependencies;
        $step_order = array_flip($steps);
        $errors = [];

        foreach ($steps as $step) {
            $dependent = $dependencies[$step]['dependencies'] ?? [];

            foreach ($dependent as $dependency) {
                if (in_array($dependency, $steps) && $step_order[$dependency] > $step_order[$step]) {
                    $errors[] = "Dependency error: $step depends on $dependency";
                }
            }
        }

        return $errors;
    }

    private function sort(array $dependencies): array
    {
        $errors = $this->checkDependencies($dependencies);

        if (count($errors)) {
            return Purchase::$steps;
        }

        return [Setup::class, ...$dependencies, Submit::class]; // Note: Re-index if you're doing any index-based checking/comparision.
    }
}
