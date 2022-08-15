<?php
/**
 * Project Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Project Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Project;
use App\Models\Quote;

class CloneQuoteToProjectFactory
{
    public static function create(Quote $quote, $user_id) : ?Project
    {
        $project = new Project();

        $project->company_id = $quote->company_id;
        $project->user_id = $user_id;
        $project->client_id = $quote->client_id;
        
        $project->public_notes = $quote->public_notes;
        $project->private_notes = $quote->private_notes;
        $project->budgeted_hours = 0;
        $project->task_rate = 0;
        $project->name = ctrans('texts.quote_number_short') . " " . $quote->number;
        $project->custom_value1 = '';
        $project->custom_value2 = '';
        $project->custom_value3 = '';
        $project->custom_value4 = '';
        $project->is_deleted = 0;

        return $project;
    }
}
