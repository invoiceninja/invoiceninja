<?php

use Illuminate\Database\Migrations\Migration;

class AddTimesheets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('client_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('description');
            
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('account_id')->references('id')->on('accounts');
            
            $t->unique(['account_id', 'name']);
        });
        
        Schema::create('project_codes', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('project_id');
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('description');
           
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            
            $t->unique(['account_id', 'name']);
        });
        
        
        Schema::create('timesheets', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->timestamps();
            $t->softDeletes();
                       
            $t->dateTime('start_date');
            $t->dateTime('end_date');
            $t->float('discount');
            
            $t->decimal('hours');
            
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('account_id')->references('id')->on('accounts');
            
            $t->unsignedInteger('public_id');
            $t->unique(['account_id', 'public_id']);
        });
        
        Schema::create('timesheet_event_sources', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->timestamps();
            $t->softDeletes();
            
            $t->string('owner');
            $t->string('name');
            $t->string('url');
            $t->enum('type', ['ical', 'googlejson']);
            
            $t->dateTime('from_date')->nullable();
            $t->dateTime('to_date')->nullable();
            
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        
        Schema::create('timesheet_events', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('timesheet_event_source_id');
            $t->unsignedInteger('timesheet_id')->nullable()->index();
            $t->unsignedInteger('project_id')->nullable()->index();
            $t->unsignedInteger('project_code_id')->nullable()->index();
            $t->timestamps();
            $t->softDeletes();
            
            // Basic fields
            $t->string('uid');
            $t->string('summary');
            $t->text('description');
            $t->string('location');
            $t->string('owner');
            $t->dateTime('start_date');
            $t->dateTime('end_date');
            
            // Calculated values
            $t->decimal('hours');
            $t->float('discount');
            $t->boolean('manualedit');
            
            // Original data
            $t->string('org_code');
            $t->timeStamp('org_created_at');
            $t->timeStamp('org_updated_at');
            $t->timeStamp('org_deleted_at')->default('0000-00-00T00:00:00');
            $t->string('org_start_date_timezone')->nullable();
            $t->string('org_end_date_timezone')->nullable();
            $t->text('org_data');
            
            // Error and merge handling
            $t->string('import_error')->nullable();
            $t->string('import_warning')->nullable();
            $t->text('updated_data')->nullable();
            $t->timeStamp('updated_data_at')->default('0000-00-00T00:00:00');
            
            $t->foreign('account_id')->references('id')->on('accounts');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('timesheet_event_source_id')->references('id')->on('timesheet_event_sources')->onDelete('cascade');

            $t->unique(['timesheet_event_source_id', 'uid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('timesheet_events');
        Schema::dropIfExists('timesheet_event_sources');
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('project_codes');
        Schema::dropIfExists('projects');
    }
}
