<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class ClientSettingsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->faker = \Faker\Factory::create();

    }

    public function testClientBaseline()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('1', $arr['data']['settings']['currency_id']);
    }

    public function testClientValidSettings()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('1', $arr['data']['settings']['currency_id']);
        $this->assertEquals('1', $arr['data']['settings']['language_id']);
        $this->assertEquals('1', $arr['data']['settings']['payment_terms']);
        $this->assertEquals(10, $arr['data']['settings']['default_task_rate']);
        $this->assertEquals(true, $arr['data']['settings']['send_reminders']);
        $this->assertEquals('1', $arr['data']['settings']['valid_until']);
    }

    public function testClientIllegalCurrency()
    {

        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Cooliox2',
            'settings' => [
                'currency_id' => 'a',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '2',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(302);
    }

    public function testClientIllegalLanguage()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => 'a',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(302);
    }

    public function testClientIllegalPaymenTerms()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => 'a',
                'valid_until' => '1',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(302);
    }

    public function testClientIllegalValidUntil()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => 'a',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(302);
    }

    public function testClientIllegalDefaultTaskRate()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 'a',
                'send_reminders' => true,
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertFalse(array_key_exists('default_task_rate', $arr));
    }

    public function testClientIllegalSendReminderBool()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 'a',
                'send_reminders' => 'faaalse',
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(302);
    }

    public function testClientSettingBools()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 'a',
                'send_reminders' => 'true',
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
    }
}
