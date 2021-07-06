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
            'vat_number' => (string)$this->faker->randomNumber(6),
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
                ->with('#update_contact', function (Browser $form) use ($original) {
                    $form
                        ->type('#client_name', $original['name'])
                        ->type('#client_vat_number', $original['vat_number'])
                        ->type('#client_phone', $original['phone'])
                        ->type('#client_website', $original['website'])
                        ->press('Save');
                })
                ->pause(2000)
                ->refresh()
                ->pause(2000);

            $updated = [
                'name' => $browser->value('#client_name'),
                'vat_number' => $browser->value('#client_vat_number'),
                'phone' => $browser->value('#client_phone'),
                'website' => $browser->value('#client_website')
            ];

            $this->assertSame($original, $updated);
        });
    }

    public function testContactDetailsUpdate()
    {
        $original = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email_address' => 'user@example.com',
            'phone' => $this->faker->phoneNumber,
        ];

        $this->browse(function (Browser $browser) use ($original) {
            $browser
                ->visitRoute('client.invoices.index')
                ->click('button[data-ref="client-profile-dropdown"]')
                ->click('a[data-ref="client-profile-dropdown-settings"]')
                ->waitForText('Client Information');

            $browser
                ->with('#update_client', function (Browser $form) use ($original) {
                    $form
                        ->type('#contact_first_name', $original['first_name'])
                        ->type('#contact_last_name', $original['last_name'])
                        ->scrollIntoView('#contact_email_address')
                        ->type('#contact_email_address', $original['email_address'])
                        ->type('#contact_phone', $original['phone'])
                        ->click('button[data-ref="update-contact-details"]');
                })
                ->pause(2000)
                ->refresh()
                ->pause(2000);

            $updated = [
                'first_name' => $browser->value('#contact_first_name'),
                'last_name' => $browser->value('#contact_last_name'),
                'email_address' => $browser->value('#contact_email_address'),
                'phone' => $browser->value('#contact_phone'),
            ];

            $this->assertSame($original, $updated);
        });
    }
}
