<?php

namespace App\Jobs\Entity;

use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActionEntity
{
    use Dispatchable;

    protected $action;

    protected $entity;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Model $entity, string $action)
    {
        $this->action = $action;
        $this->entity = $entity;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(BaseRepository $baseRepo)
    {
        return $baseRepo->{$this->action}($this->entity);
    }
}
