<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\WithoutMiddleware;


class CreateAccountTest extends DuskTestCase
{

    use WithFaker;
    use DatabaseTransactions;


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
        /*
        $response = $this->post('/signup', [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'terms_of_service' => 1,
            'privacy_policy' => 1,
            'email' => config('ninja.testvars.username'),
            'password' => config('ninja.testvars.password')
        ]);


        $response->assertSuccessful();
        */

        $this->visit('/signup')
            ->type($this->faker->firstName(), 'first_name')
            ->type($this->faker->lastName(), 'last_name')
            ->type($this->faker->email(), 'email')
            ->type($this->faker->password(7), 'password')
            ->check('terms_of_service')
            ->check('terms_of_service')
            ->press(trans('texts.create_account'))
            ->seePageIs('/dashboard');

    }

}
