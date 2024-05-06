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

/**
 * @test
 */
class TaskRoundingTest extends TestCase
{

    public int $task_round_to_nearest = 1;

    public bool $task_round_up = true;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function roundTimeLog(int $start_time, int $end_time): int
    {
        if($this->task_round_to_nearest == 1)
            return $end_time;

        $interval = $end_time - $start_time;

        if($this->task_round_up)
            return $start_time + (int)ceil($interval/$this->task_round_to_nearest)*$this->task_round_to_nearest;

        return $start_time - (int)floor($interval/$this->task_round_to_nearest) * $this->task_round_to_nearest;

    }

}
