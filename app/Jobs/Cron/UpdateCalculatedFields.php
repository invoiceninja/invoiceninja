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

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use App\Models\Project;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Auth;

class UpdateCalculatedFields
{
    use Dispatchable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        nlog("Updating calculated fields");

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {

            Project::query()->with('tasks')->whereHas('tasks', function ($query) {
                $query->where('updated_at', '>', now()->subHours(2));
            })
                ->cursor()
                ->each(function ($project) {

                    $project->current_hours = $this->calculateDuration($project);
                    $project->save();
                });



        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);


                Project::query()->with('tasks')->whereHas('tasks', function ($query) {
                    $query->where('updated_at', '>', now()->subHours(2));
                })
                    ->cursor()
                    ->each(function ($project) {
                        $project->current_hours = $this->calculateDuration($project);
                        $project->save();
                    });

            }
        }
    }

    private function calculateDuration($project): int
    {
        $duration = 0;

        $project->tasks->each(function ($task) use (&$duration) {

            if(is_iterable(json_decode($task->time_log))) {

                foreach(json_decode($task->time_log) as $log) {

                    $start_time = $log[0];
                    $end_time = $log[1] == 0 ? time() : $log[1];

                    $duration += $end_time - $start_time;

                }
            }

        });

        return round(($duration / 60 / 60), 0);

    }
}
