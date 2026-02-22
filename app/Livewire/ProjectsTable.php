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

namespace App\Livewire;

use App\Libraries\MultiDB;
use App\Models\Project;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectsTable extends Component
{
    use WithSorting;
    use WithPagination;

    public $per_page = 10;

    public $company_id;

    public $db;

    public function mount()
    {
        MultiDB::setDb($this->db);

        $this->sort_asc = false;
        $this->sort_field = 'name';
    }

    public function render()
    {
        $query = Project::query()
            ->select([
                'id',
                'user_id',
                'assigned_user_id',
                'company_id',
                'client_id',
                'name',
                'task_rate',
                'due_date',
                // 'private_notes', // Excluded
                'budgeted_hours',
                'custom_value1',
                'custom_value2',
                'custom_value3',
                'custom_value4',
                'created_at',
                'updated_at',
                'deleted_at',
                'public_notes',
                'is_deleted',
                'number',
                'color',
                'current_hours',
            ])
            ->where('company_id', $this->company_id)
            ->where('is_deleted', false)
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->withCount(['tasks' => function ($query) {
                $query->where('is_deleted', false);
            }])
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.projects-table', [
            'projects' => $query,
        ]);
    }
}
