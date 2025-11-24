<?php






use Illuminate\Database\Migrations\Migration;


use Illuminate\Database\Schema\Blueprint;


use Illuminate\Support\Facades\Schema;





return new class extends Migration


{


    /**


     * Run the migrations.


     */


    public function up(): void


    {


        Schema::table('users', function (Blueprint $table) {


            $table->integer('google_2fa_ts')->nullable()->after('google_2fa_secret');


        });


    }





    /**


     * Reverse the migrations.


     */


    public function down(): void


    {


        Schema::table('users', function (Blueprint $table) {


            $table->dropColumn('google_2fa_ts');


        });


    }


};
