<?php

use App\Models\Gateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Model::unguard();

        $fields = new \stdClass;
        $fields->accessToken = '';
        $fields->applicationId = '';
        $fields->locationId = '';
        $fields->testMode = false;

        $square = new Gateway();
        $square->id = 57;
        $square->name = 'Square';
        $square->provider = 'Square';
        $square->key = '65faab2ab6e3223dbe848b1686490baz';
        $square->sort_order = 4343;
        $square->is_offsite = false;
        $square->visible = true;
        $square->fields = json_encode($fields);
        $square->save();
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
};
