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

use Tests\TestCase;
use App\Models\Task;
use App\Models\Client;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\DataMapper\ClientSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 */
class TaskRoundingTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public int $task_round_to_nearest = 1;

    public bool $task_round_up = true;

    private $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testRoundDownToMinute()
    {
        $start_time = 1718071646;
        $end_time = 1718078906;
        $this->task_round_to_nearest = 60;
        $this->task_round_up = false;

        $rounded = $start_time + 7260;

        $this->assertEquals($rounded, $end_time);
        $this->assertEquals($rounded, $this->roundTimeLog($start_time, $end_time));

    }

    public function testRoundUp()
    {
        $start_time = 1714942800;
        $end_time = 1714943220; //7:07am
        $this->task_round_to_nearest = 600;

        //calculated time = 7:10am
        $rounded = 1714943400;

        $this->assertEquals($rounded, $this->roundTimeLog($start_time, $end_time));

    }

    public function testRoundUp2()
    {

        $start_time = 1715237056;
        $end_time = $start_time + 60 * 7;
        $this->task_round_to_nearest = 600;

        $rounded = $start_time + 60 * 10;

        $this->assertEquals($rounded, $this->roundTimeLog($start_time, $end_time));


    }

    public function testRoundUp3()
    {


        $start_time = 1715213100;
        $end_time = $start_time + 60 * 15;
        $this->task_round_to_nearest = 900;

        $rounded = $start_time + 60 * 15;

        $this->assertEquals($rounded, $this->roundTimeLog($start_time, $end_time));

        // $s = \Carbon\Carbon::createFromTimestamp($start_time);
        // $e = \Carbon\Carbon::createFromTimestamp($end_time);
        // $x = \Carbon\Carbon::createFromTimestamp($rounded);
        // echo $s->format('Y-m-d H:i:s').PHP_EOL;
        // echo $e->format('Y-m-d H:i:s').PHP_EOL;
        // echo $x->format('Y-m-d H:i:s').PHP_EOL;

    }

    public function testRoundUp4()
    {

        $start_time = 1715238000;
        $end_time = 1715238900;

        $this->task_round_to_nearest = 900;

        $rounded = $start_time + 60 * 15;

        // $s = \Carbon\Carbon::createFromTimestamp($start_time);
        // $e = \Carbon\Carbon::createFromTimestamp($end_time);
        // $x = \Carbon\Carbon::createFromTimestamp($rounded);
        // echo $s->format('Y-m-d H:i:s').PHP_EOL;
        // echo $e->format('Y-m-d H:i:s').PHP_EOL;
        // echo $x->format('Y-m-d H:i:s').PHP_EOL;


        $this->assertEquals($rounded, $this->roundTimeLog($start_time, $end_time));


    }

    public function testRoundingViaBulkAction()
    {

        $this->company->settings->default_task_rate = 41;
        $this->company->settings->task_round_to_nearest = 900;
        $this->company->settings->task_round_up = true;
        $this->company->saveSettings($this->company->settings, $this->company);

        $settings = ClientSettings::defaults();
        $settings->default_task_rate = 41;
        $settings->task_round_to_nearest = 900;
        $settings->task_round_up = true;

        $c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            // 'settings' => $settings,
        ]);


        $var = time() - 800;

        $data = [
            'client_id' => $c->hashed_id,
            'description' => 'Test Task',
            'time_log' => '[[1681165417,1681165432,"sumtin",true],['.$var.',0]]',
            'assigned_user' => [],
            'project' => [],
            'user' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks/", $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $i = $arr['data']['id'];

        $data = [
            'ids' => [$i],
            'action' => 'stop'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/tasks/bulk", $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $task = Task::find($this->decodePrimaryKey($i));
    }

    public function testRoundDown()
    {
        $start_time = 1714942800;
        $end_time = 1714943220; //7:07am
        $this->task_round_to_nearest = 600;
        $this->task_round_up = false;

        //calculated time = 7:10am
        $rounded = $start_time;

        $this->assertEquals($rounded, $this->roundTimeLog($start_time, $end_time));

    }

    // public function roundTimeLog(int $start_time, int $end_time): int
    // {
    //     if($this->task_round_to_nearest == 1)
    //         return $end_time;

    //     $interval = $end_time - $start_time;

    //     if($this->task_round_up)
    //         return $start_time + (int)ceil($interval/$this->task_round_to_nearest)*$this->task_round_to_nearest;

    //     return $start_time - (int)floor($interval/$this->task_round_to_nearest) * $this->task_round_to_nearest;

    // }

    public function roundTimeLog(int $start_time, int $end_time): int
    {
        if($this->task_round_to_nearest == 1 || $end_time == 0) {
            return $end_time;
        }

        $interval = $end_time - $start_time;

        if($this->task_round_up) {
            return $start_time + (int)ceil($interval / $this->task_round_to_nearest) * $this->task_round_to_nearest;
        }

        if($interval <= $this->task_round_to_nearest) {
            return $start_time;
        }

        return $start_time + (int)floor($interval / $this->task_round_to_nearest) * $this->task_round_to_nearest;

    }


}
