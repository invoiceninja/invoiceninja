<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddThaisarabunFont extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		DB::table('fonts')->insert(
		[
			'folder' => 'thsarabunpsk', 
			'name' => 'THSarabunPSK - Thai',
			'css_stack' => '',
            'google_font' => '',
            'normal' => 'THSarabun.ttf',
            'bold' => 'THSarabun Bold.ttf',
            'italics' => 'THSarabun Italic.ttf',
            'bolditalics' => 'THSarabun BoldItalic.ttf',
            'sort_order' => 1800,
		]
		);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		$font = \App\Models\Font::whereFolder('thsarabunpsk')->first();
		$font->delete();
    }
}
