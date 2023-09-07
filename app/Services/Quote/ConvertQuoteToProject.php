<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quote;

use App\Models\Quote;
use App\Factory\ProjectFactory;
use App\Models\Project;
use App\Utils\Traits\GeneratesCounter;

class ConvertQuoteToProject
{
    use GeneratesCounter;

    public function __construct(private Quote $quote)
    {
    }

    public function run(): Project
    {

        $quote_items = collect($this->quote->line_items);

        $project = ProjectFactory::create($this->quote->company_id, $this->quote->user_id);
        $project->name = ctrans('texts.quote_number_short'). " " . $this->quote->number . "[{$this->quote->client->present()->name()}]";
        $project->client_id = $this->quote->client_id;
        $project->public_notes = $this->quote->public_notes;
        $project->private_notes = $this->quote->private_notes;
        $project->budgeted_hours = $quote_items->sum('quantity') ?? 0;
        $project->task_rate = ($this->quote->amount / $project->budgeted_hours) ?? 0;
        $project->saveQuietly();
        $project->number = $this->getNextProjectNumber($project);
        $project->saveQuietly();

        $this->quote->project_id = $project->id;
        $this->quote->saveQuietly();

        event('eloquent.created: App\Models\Project', $project);

            $quote_items->each(function($item){

            });

        return $project;
    }
}