<?php

use Illuminate\Database\Migrations\Migration;

class SimplifyTasks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tasks = \App\Models\Task::all();

        foreach ($tasks as $task) {
            $startTime = strtotime($task->start_time);
            if (! $task->time_log || ! count(json_decode($task->time_log))) {
                $task->time_log = json_encode([[$startTime, $startTime + $task->duration]]);
                $task->save();
            } elseif ($task->getDuration() != intval($task->duration)) {
                $task->time_log = json_encode([[$startTime, $startTime + $task->duration]]);
                $task->save();
            }
        }

        Schema::table('tasks', function ($table) {
            $table->dropColumn('start_time');
            $table->dropColumn('duration');
            $table->dropColumn('break_duration');
            $table->dropColumn('resume_time');
        });

        Schema::table('users', function ($table) {
            $table->boolean('dark_mode')->default(false)->nullable();
        });

        Schema::table('users', function ($table) {
            $table->dropColumn('theme_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tasks', function ($table) {
            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->nullable();
            $table->timestamp('resume_time')->nullable();
            $table->integer('break_duration')->nullable();
        });

        if (Schema::hasColumn('accounts', 'dark_mode')) {
            Schema::table('users', function ($table) {
                $table->dropColumn('dark_mode');
            });
        }
        Schema::table('users', function ($table) {
            $table->integer('theme_id')->nullable();
        });
    }
}
