<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Report;

use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Client;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use App\Export\CSV\BaseExport;
use App\Models\Project;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Services\Template\TemplateService;

class ProjectReport extends BaseExport
{
    use MakesDates;

    private string $template = '/views/templates/reports/project_report.html';

    /**
        @param array $input
        [
            'date_range',
            'start_date',
            'end_date',
            'projects',
        ]
    */
    public function __construct(public Company $company, public array $input)
    {
    }

    public function run()
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        return $this->getPdf();
    }

    public function getPdf()
    {
        $user = isset($this->input['user_id']) ? User::withTrashed()->find($this->input['user_id']) : $this->company->owner();

        $user_name = $user ? $user->present()->name() : '';

        $query = \App\Models\Project::with(['invoices','expenses','tasks'])
                                ->where('company_id', $this->company->id);

        $query = $this->filterByUserPermissions($query);

        $projects = &$this->input['projects'];

        if ($projects) {

            $transformed_projects = is_string($projects) ? $this->transformKeys(explode(',', $projects)) : $this->transformKeys($projects);

            if (count($transformed_projects) > 0) {
                $query->whereIn('id', $transformed_projects);
            }

        }

        $clients = &$this->input['clients'];

        if ($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        $data = [
            'projects' => $query->get(),
            'company_logo' => $this->company->present()->logo(),
            'company_name' => $this->company->present()->name(),
            'created_on' => $this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale()),
            'created_by' => $user_name,
            // 'charts' => $this->getCharts($projects),
        ];

        $ts = new TemplateService();

        /** @var ?Project $_project */
        $_project = $query->first();

        $currency_code = $_project?->client ? $_project->client->currency()->code : $this->company->currency()->code;

        $ts_instance = $ts->setCompany($this->company)
                    // ->setData($data)
                    ->processData($data)
                    ->setRawTemplate(file_get_contents(resource_path($this->template)))
                    ->addGlobal(['currency_code' => $currency_code])
                    ->setGlobals()
                    ->parseNinjaBlocks()
                    ->save();


        return $ts_instance->getPdf();
    }

    // private function getTaskAllocationData(Project $project)
    // {
    //     $tasks = $project->tasks()->withTrashed()->map(function ($task) {

    //         return [
    //             'label' => strlen($task->description ?? '') > 0 ? $task->description : $task->number,
    //             'hours' => ($task->calcDuration() / 3600)
    //         ];

    //     });

    //     $taskAllocationData = [
    //         'labels' => $tasks->pluck('label'),
    //         'datasets' => [
    //             [
    //                 'label' => 'Hours Spent',
    //                 'data' => $tasks->pluck('hours'),
    //                 'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
    //                 'borderColor' => 'rgba(54, 162, 235, 1)',
    //                 'borderWidth' => 1
    //             ]
    //         ]
    //     ];

    //     return $taskAllocationData;

    // }

    // private function getCharts(array $projects)
    // {

    //     if(!class_exists(Modules\Admin\Services\ChartService::class)) {
    //         return [];
    //     }

    //     $chartService = new Modules\Admin\Services\ChartService();

    //     return $projects->map(function ($project) use ($chartService) {
    //         return [
    //             'id' => $project->hashed_id,
    //             'budgeted_hours' => $chartService->getBudgetedHours($project),
    //         ];
    //     });
    // }
}
