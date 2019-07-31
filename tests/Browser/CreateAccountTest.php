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

}
