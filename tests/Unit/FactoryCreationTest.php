<?php

namespace Tests\Unit;

use App\Factory\UserFactory;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Factory\
 */
class FactoryCreationTest extends TestCase
{
    use MakesHash;

    public function setUp() :void
    {
    
        parent::setUp();
    
        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

    }

    public function testUserCreate()
    {
        $new_user = UserFactory::create();
        $new_user->email = $this->faker->email;
        $new_user->save();

        $this->assertNotNull($new_user);

        $this->assertInternalType("int", $new_user->id);

    }


}
