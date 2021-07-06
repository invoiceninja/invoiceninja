<?php

namespace Tests\Browser\ClientPortal;

use Faker\Factory;
use Faker\Generator;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\ClientPortal\Login;
use Tests\DuskTestCase;

class ProfileSettingsTest extends DuskTestCase
{
    /** @var Generator */
    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        foreach (static::$browsers as $browser) {
            $browser->driver->manage()->deleteAllCookies();
        }

        $this->browse(function (Browser $browser) {
            $browser
                ->visit(new Login())
                ->auth();
        });
    }

    public function testPageLoads()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('button[data-ref="client-profile-dropdown"]')
                ->click('a[data-ref="client-profile-dropdown-settings"]')
                ->waitForText('Client Information')
                ->assertSeeIn('span[data-ref="meta-title"]', 'Client Information')
                ->visitRoute('client.logout');
        });
    }

    public function testClientDetailsUpdate()
    {
        $original = [
            'name' => $this->faker->name,
            'vat_number' => $this->faker->randomNumber(6),
            'phone' => $this->faker->phoneNumber,
            'website' => $this->faker->url,
        ];

        $this->browse(function (Browser $browser) use ($original) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('button[data-ref="client-profile-dropdown"]')
                ->click('a[data-ref="client-profile-dropdown-settings"]')
                ->waitForText('Client Information');

            $browser
                ->type('name', $original['name'])
                ->type('vat_number', $original['vat_number'])
                ->type('phone', $original['phone'])
                ->type('website', $original['website'])
                ->press('Save')
                ->refresh();

            $updated = [
                'name' => $browser->inputValue('name'),
                'vat_number' => $browser->inputValue('vat_number'),
                'phone' => $browser->inputValue('phone'),
                'website' => $browser->inputValue('website')
            ];

            $this->assertNotSame($original, $updated);
        });
    }
}
