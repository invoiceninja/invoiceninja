<?php

namespace Tests\Unit;

use App\DataMapper\Client;
use Illuminate\Support\Facades\Log;
use JsonMapper;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\NumberHelper
 */
class JsonMappingTest extends TestCase
{

    public function setUp()
    {
    
    parent::setUp();

    	$test_data = [
    			'field_visibility' => [
    				[
    					'name' => '__checkbox',
    					'is_visible' => true,
	    			],
	    			[	
	    				'name' => 'name',
	    				'is_visible' => true,
	    			],
	    			[	
	    				'name' => 'contact',
	    				'is_visible' => true,
	    			],
	    			[	
	    				'name' => 'email',
	    				'is_visible' => true,
	    			],
	    			[
	    				'name' => 'client_created_at',
	    				'is_visible' => true,
	    			],
	    			[
	    				'name' => 'last_login',
	    				'is_visible' => true,
	    			],
	    			[
	    				'name' => 'balance',
	    				'is_visible' => true,
	    			],
	    			[
	    				'name' => '__component:client-actions',
	    				'is_visible' => true,
	    			],
    			],
    			'per_page' => 20
    		
    	];

    	$this->map = $test_data;

    	$this->jsonMapper = new JsonMapper();
    }

   public function testMapArrayStrangeKeys()
    {
        $mapped = $this->jsonMapper->mapArray(
            ['en-US' => 'foo', 'de-DE' => 'bar'],
            []
        );
        $this->assertEquals(['en-US' => 'foo', 'de-DE' => 'bar'], $mapped);
    }
}


	