<?php

use App\Models\InvoiceDesign;

class InvoiceDesignsSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $designs = [
            'Clean',
            'Bold',
            'Modern',
            'Plain',
            'Business',
            'Creative',
            'Elegant',
            'Hipster',
            'Playful',
            'Photo',
        ];
        
        for ($i=0; $i<count($designs); $i++) {
            $design = $designs[$i];
            $fileName = storage_path() . '/templates/' . strtolower($design) . '.js';
            if (file_exists($fileName)) {
                $pdfmake = file_get_contents($fileName);
                if ($pdfmake) {
                    $record = InvoiceDesign::whereName($design)->first();
                    if (!$record) {
                        $record = new InvoiceDesign;
                        $record->id = $i + 1;
                        $record->name = $design;
                    }
                    $record->pdfmake = $pdfmake;
                    $record->save();
                }
            }
        }
    }

}
