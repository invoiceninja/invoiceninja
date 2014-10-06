<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTimesheets extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{ 
        Schema::create('projects', function($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->timestamps();
            $t->softDeletes();

            $t->string('name');
            $t->string('description');
            
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('account_id')->references('id')->on('accounts'); 
            
            $t->unique( array('account_id','name') );
        });
        
        Schema::create('project_codes', function($t) {
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
            
            $t->unique( array('account_id','name') );
        });
        
        
        Schema::create('timesheets', function($t) {
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
            $t->unique( array('account_id','public_id') );
        });
        
        Schema::create('timesheet_event_sources', function($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->timestamps();
            $t->softDeletes();
            
            $t->string('owner');
            $t->string('name');
            $t->string('url');
            $t->enum('type', array('ical', 'googlejson'));
            
            $t->foreign('account_id')->references('id')->on('accounts'); 
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        
        Schema::create('timesheet_events', function($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('account_id')->index();
            $t->unsignedInteger('timesheet_event_source_id');
            $t->unsignedInteger('timesheet_id')->nullable()->index();
            $t->unsignedInteger('project_id')->nullable()->index();
            $t->unsignedInteger('project_code_id')->nullable()->index();            
            $t->timestamps();
            $t->softDeletes();
            
            $t->string('summary');
            $t->string('uid');
            $t->text('description');
            $t->string('location');
            $t->string('owner');
            
            $t->dateTime('start_date');
            $t->dateTime('end_date');
            $t->decimal('hours');
            $t->float('discount');
            
            $t->timeStamp('org_created_at');
            $t->timeStamp('org_updated_at');
            $t->string('org_start_date_timezone')->nullable();
            $t->string('org_end_date_timezone')->nullable();
            $t->text('org_data');
                        
            $t->foreign('account_id')->references('id')->on('accounts'); 
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('timesheet_event_source_id')->references('id')->on('timesheet_event_sources')->onDelete('cascade');

            $t->unique( array('timesheet_event_source_id', 'uid') );
        });
    }

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::drop('timesheet_events');
        Schema::drop('timesheet_event_sources');
        Schema::drop('timesheets');
        Schema::drop('project_codes');
        Schema::drop('projects');
	}

}
