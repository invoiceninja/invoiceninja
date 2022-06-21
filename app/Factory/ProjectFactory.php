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

namespace App\Factory;

use App\Models\Project;

class ProjectFactory
{
    public static function create(int $company_id, int $user_id) :Project
    {
        $project = new Project;
        $project->company_id = $company_id;
        $project->user_id = $user_id;

        $project->public_notes = '';
        $project->private_notes = '';
        $project->budgeted_hours = 0;
        $project->task_rate = 0;
        $project->name = '';
        $project->custom_value1 = '';
        $project->custom_value2 = '';
        $project->custom_value3 = '';
        $project->custom_value4 = '';
        $project->is_deleted = 0;

        return $project;
    }
}
