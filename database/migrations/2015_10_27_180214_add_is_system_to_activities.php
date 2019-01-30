<?php

use App\Models\Activity;
use Illuminate\Database\Migrations\Migration;

class AddIsSystemToActivities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function ($table) {
            $table->boolean('is_system')->default(0);
        });

        $activities = Activity::where('message', 'like', '%<i>System</i>%')->get();
        foreach ($activities as $activity) {
            $activity->is_system = true;
            $activity->save();
        }

        Schema::table('activities', function ($table) {
            $table->dropColumn('message');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function ($table) {
            $table->dropColumn('is_system');
        });

        Schema::table('activities', function ($table) {
            $table->text('message')->nullable();
        });
    }
}
