<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Dusk\Browser;

class CreateAccountTest extends DuskTestCase
{

    use WithFaker;


    public function testSignupFormDisplayed()
    {
        $response = $this->get('/signup');
        $response->assertStatus(200);
    }
    /**
     * A valid user can be logged in.
     *
     * @return void
     */
    public function testCreateAValidUser()
    {

        $this->browse(function (Browser $browser) {
            $browser->visit('/signup')
                ->type('first_name',$this->faker->firstName())
                ->type('last_name', $this->faker->lastName())
                ->type('email', $this->faker->email())
                ->type('password', $this->faker->password(7))
                ->check('terms_of_service')
                ->check('privacy_policy')
                ->press(trans('texts.create_account'))
                ->assertPathIs('/dashboard');
        });

    }

}
