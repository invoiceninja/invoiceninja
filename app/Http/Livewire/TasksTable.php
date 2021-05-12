<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Livewire;

use App\Models\Task;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class TasksTable extends Component
{
    use WithSorting;
    use WithPagination;

    public $per_page = 10;

    public function render()
    {
        $query = Task::query()
            ->where('client_id', auth('contact')->user()->client->id)
            ->whereNotNull('invoice_id')
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.tasks-table', [
            'tasks' => $query,
        ]);
    }
}
