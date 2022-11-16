<?php

use App\Models\Design;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\PdfMaker\Design as PdfMakerDesign;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        $design = ['name' => 'Calm', 'user_id' => null, 'company_id' => null, 'is_custom' => false, 'design' => '', 'is_active' => true];

        $design = Design::create($design);

        $template = new PdfMakerDesign(strtolower($design->name));
        $template->document();

        $design_object = new \stdClass;
        $design_object->includes = $template->getSectionHTML('style');
        $design_object->header = $template->getSectionHTML('header');
        $design_object->body = $template->getSectionHTML('body');
        $design_object->product = '';
        $design_object->task = '';
        $design_object->footer = $template->getSectionHTML('footer');

        $design->design = $design_object;
        $design->save();


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
