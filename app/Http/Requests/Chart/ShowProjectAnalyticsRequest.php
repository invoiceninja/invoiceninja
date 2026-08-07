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

namespace App\Http\Requests\Chart;

use App\Http\Requests\Request;
use App\Models\Project;
use App\Utils\Traits\MakesDates;

class ShowProjectAnalyticsRequest extends Request
{
    use MakesDates;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $project = $this->project();

        return $user
            && $project
            && ! $project->is_deleted
            // && ($user->isAdmin() || $user->hasPermission('view_dashboard'))
            && $user->can('view', $project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_range' => 'bail|sometimes|string|in:last7_days,last30_days,last365_days,this_month,last_month,this_quarter,last_quarter,this_year,last_year,all_time,custom',
            'start_date' => 'bail|sometimes|date',
            'end_date' => 'bail|sometimes|date',
            'include_drafts' => 'bail|sometimes|boolean',
        ];
    }

    public function prepareForValidation(): void
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        $input = $this->all();
        $input['include_drafts'] = filter_var($input['include_drafts'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($user && isset($input['date_range'])) {
            $dates = $this->calculateStartAndEndDates($input, $user->company());
            $input['start_date'] = $dates[0];
            $input['end_date'] = $dates[1];
        }

        $this->replace($input);
    }

    public function project(): ?Project
    {
        $project = $this->route('project');

        return $project instanceof Project ? $project : null;
    }
}
