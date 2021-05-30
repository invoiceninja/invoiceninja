<?php

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeDocumentsAssignedUserNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('documents', function (Blueprint $table){
            $table->unsignedInteger('assigned_user_id')->nullable()->change();
        });

        Document::where('assigned_user_id', 0)->update(['assigned_user_id' => null]);

        if(config('ninja.db.multi_db_enabled')){
            Document::on('db-ninja-01')->where('assigned_user_id', 0)->update(['assigned_user_id' => null]);
            Document::on('db-ninja-02')->where('assigned_user_id', 0)->update(['assigned_user_id' => null]);
        }
        else{
            Document::where('assigned_user_id', 0)->update(['assigned_user_id' => null]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
