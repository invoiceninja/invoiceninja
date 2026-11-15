<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Task;
use Tests\TestCase;

class TaskCompanyValueTest extends TestCase
{
    public function testTaskWithoutClientUsesCompanyCurrencyAndRate(): void
    {
        $company = new Company();
        $company->settings = (object) [
            'currency_id' => '1',
            'default_task_rate' => 80,
        ];

        $task = new Task();
        $task->setRawAttributes([
            'client_id' => null,
            'rate' => 0,
            'time_log' => json_encode([
                [1793491200, 1793496600, '', true],
                [1793496600, 1793500200, '', false],
            ], JSON_THROW_ON_ERROR),
        ]);
        $task->setRelation('client', null);
        $task->setRelation('project', null);
        $task->setRelation('company', $company);

        $this->assertSame(120.0, $task->taskCompanyValue());
    }
}
