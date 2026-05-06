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

namespace App\Jobs\Ninja;

use App\Libraries\MultiDB;
use App\Models\Scheduler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class CompanyTaskRunner implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public $tries = 1;

    public function __construct(public int $company_id, public ?string $db) {}

    public function handle(): void
    {
        Auth::logout();

        if ($this->db) {
            MultiDB::setDB($this->db);
        }

        Scheduler::with('company')
            ->where('company_id', $this->company_id)
            ->where('is_paused', false)
            ->where('is_deleted', false)
            ->whereNotNull('next_run')
            ->where('next_run', '<=', now())
            ->where(function ($q) {
                $q->where('remaining_cycles', '!=', 0)
                  ->orWhereNull('remaining_cycles');
            })
            ->orderBy('next_run')
            ->cursor()
            ->each(function ($scheduler) {

                nlog("Doing job ::{$scheduler->id}:: {$scheduler->name}");

                try {
                    /** @var \App\Models\Scheduler $scheduler */
                    $scheduler->service()->runTask();
                } catch (\Throwable $e) {
                    nlog("Exception:: CompanyTaskRunner:: #{$scheduler->id}:: " . $e->getMessage());

                    if (app()->bound('sentry')) {
                        app('sentry')->captureException($e);
                    }
                }
            });
    }
}
