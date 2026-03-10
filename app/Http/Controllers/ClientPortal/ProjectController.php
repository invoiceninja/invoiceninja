<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use Carbon\Carbon;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Utils\Traits\MakesDates;
use App\Http\Controllers\Controller;
use App\Services\Project\ProjectService;
use App\Http\Requests\ClientPortal\Projects\ShowProjectRequest;

class ProjectController extends Controller
{
    use MakesDates;

    /**
     * Display listing of client projects
     */
    public function index()
    {
        return $this->render('projects.index');
    }

    /**
     * Display a specific project with its tasks
     *
     * @param  ShowProjectRequest  $request
     * @param  Project  $project
     * @return \Illuminate\View\View
     */
    public function show(ShowProjectRequest $request, Project $project)
    {
        $show_all_tasks_client_portal = auth()->guard('contact')->user()->client->getSetting('show_all_tasks_client_portal');

        // Load tasks with conditional filtering
        $project->loadCount(['tasks' => function ($query) {
            $query->where('is_deleted', false);
        }]);

        $project->load(['tasks' => function ($query) use ($show_all_tasks_client_portal) {
            $query->where('is_deleted', false)
                ->where('client_id', auth()->guard('contact')->user()->client_id)
                ->when($show_all_tasks_client_portal === 'invoiced', function ($q) {
                    $q->whereNotNull('invoice_id');
                })
                ->when($show_all_tasks_client_portal === 'uninvoiced', function ($q) {
                    $q->whereNull('invoice_id');
                })
                ->with('status')
                ->orderBy('created_at', 'desc');
        }]);

        $timelineData = (new ProjectService($project))->timelineData();

        return $this->render('projects.show', [
            'project' => $project,
            'hours_logged' => $timelineData['hours_used'],
            'hours_invoiced' => $timelineData['hours_invoiced'],
            'hours_uninvoiced' => $timelineData['hours_uninvoiced'],
            'show_item_description' => $project->company->invoice_task_item_description,
            'due_date' => empty($project->due_date) ? '' : Carbon::parse($project->due_date)->format($project->company->date_format()),
        ]);
    }
}
