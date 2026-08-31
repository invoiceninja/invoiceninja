<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Definitions;

class TaskMap
{
    public static function importable()
    {
        return [
            0 => 'task.number',
            1 => 'task.user_id',
            2 => 'task.rate',
            3 => 'project.name',
            4 => 'client.name',
            5 => 'client.email',
            6 => 'task.description',
            7 => 'task.billable',
            8 => 'task.start_date',
            9 => 'task.end_date',
            10 => 'task.start_time',
            11 => 'task.end_time',
            12 => 'task.duration',
            13 => 'task.due_date',
            14 => 'task.estimated_duration',
            15 => 'task.status',
            16 => 'task.custom_value1',
            17 => 'task.custom_value2',
            18 => 'task.custom_value3',
            19 => 'task.custom_value4',
            20 => 'task.notes',
        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.task_number',
            1 => 'texts.user',
            2 => 'texts.task_rate',
            3 => 'texts.project',
            4 => 'texts.client',
            5 => 'texts.client_email',
            6 => 'texts.description',
            7 => 'texts.billable',
            8 => 'texts.start_date',
            9 => 'texts.end_date',
            10 => 'texts.start_time',
            11 => 'texts.end_time',
            12 => 'texts.duration',
            13 => 'texts.due_date',
            14 => 'texts.estimated_duration',
            15 => 'texts.status',
            16 => 'texts.task1',
            17 => 'texts.task2',
            18 => 'texts.task3',
            19 => 'texts.task4',
            20 => 'texts.notes',
        ];
    }
}
