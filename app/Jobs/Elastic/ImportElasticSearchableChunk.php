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

namespace App\Jobs\Elastic;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Scout\Events\ModelsImported;
use ReflectionMethod;

class ImportElasticSearchableChunk implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    /**
     * Create a new job instance.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public string $modelClass,
        public ?string $databaseConnection,
        public int|string $fromId,
        public int|string $toId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $query = $this->makeImportBuilder();
        $qualifiedIdColumn = $query->qualifyColumn('id');

        $models = $query
            ->whereBetween($qualifiedIdColumn, [$this->fromId, $this->toId])
            ->orderBy($qualifiedIdColumn)
            ->get();

        if ($models->isEmpty()) {
            return;
        }

        $models->filter->shouldBeSearchable()->searchableSync();

        event(new ModelsImported($models));
    }

    private function makeImportBuilder(): \Illuminate\Database\Eloquent\Builder
    {
        $modelClass = $this->modelClass;
        $model = new $modelClass;

        if ($this->databaseConnection !== null) {
            $model->setConnection($this->databaseConnection);
        }

        $softDelete = in_array(SoftDeletes::class, class_uses_recursive($this->modelClass), true)
            && config('scout.soft_delete', false);

        $query = $model->newQuery();

        $makeAllSearchableUsing = new ReflectionMethod($this->modelClass, 'makeAllSearchableUsing');
        $makeAllSearchableUsing->setAccessible(true);
        $query = $makeAllSearchableUsing->invoke($model, $query);

        if ($softDelete) {
            $query->withTrashed();
        }

        return $query;
    }
}
