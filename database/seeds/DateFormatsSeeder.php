<?php

use App\Models\DateFormat;
use App\Models\DatetimeFormat;

class DateFormatsSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        // Date formats
        $formats = [
            ['format' => 'd/M/Y', 'picker_format' => 'dd/M/yyyy', 'label' => '10/Mar/2013'],
            ['format' => 'd-M-Y', 'picker_format' => 'dd-M-yyyy', 'label' => '10-Mar-2013'],
            ['format' => 'd/F/Y', 'picker_format' => 'dd/MM/yyyy', 'label' => '10/March/2013'],
            ['format' => 'd-F-Y', 'picker_format' => 'dd-MM-yyyy', 'label' => '10-March-2013'],
            ['format' => 'M j, Y', 'picker_format' => 'M d, yyyy', 'label' => 'Mar 10, 2013'],
            ['format' => 'F j, Y', 'picker_format' => 'MM d, yyyy', 'label' => 'March 10, 2013'],
            ['format' => 'D M j, Y', 'picker_format' => 'D MM d, yyyy', 'label' => 'Mon March 10, 2013'],
            ['format' => 'Y-m-d', 'picker_format' => 'yyyy-mm-dd', 'label' => '2013-03-10'],
            ['format' => 'd-m-Y', 'picker_format' => 'dd-mm-yyyy', 'label' => '20-03-2013'],
            ['format' => 'm/d/Y', 'picker_format' => 'mm/dd/yyyy', 'label' => '03/20/2013']
        ];
        
        foreach ($formats as $format) {
            $record = DateFormat::whereLabel($format['label'])->first();
            if ($record) {
                $record->format = $format['format'];
                $record->picker_format = $format['picker_format'];
                $record->save();
            } else {
                DateFormat::create($format);
            }
        }

        // Date/time formats
        $formats = [
            [
                'format' => 'd/M/Y g:i a',
                'format_moment' => 'DD/MMM/YYYY h:mm:ss a',
                'label' => '10/Mar/2013'
            ],
            [
                'format' => 'd-M-Y g:i a',
                'format_moment' => 'DD-MMM-YYYY h:mm:ss a',
                'label' => '10-Mar-2013'
            ],
            [
                'format' => 'd/F/Y g:i a',
                'format_moment' => 'DD/MMMM/YYYY h:mm:ss a',
                'label' => '10/March/2013'
            ],
            [
                'format' => 'd-F-Y g:i a',
                'format_moment' => 'DD-MMMM-YYYY h:mm:ss a',
                'label' => '10-March-2013'
            ],
            [
                'format' => 'M j, Y g:i a',
                'format_moment' => 'MMM D, YYYY h:mm:ss a',
                'label' => 'Mar 10, 2013 6:15 pm'
            ],
            [
                'format' => 'F j, Y g:i a',
                'format_moment' => 'MMMM D, YYYY h:mm:ss a',
                'label' => 'March 10, 2013 6:15 pm'
            ],
            [
                'format' => 'D M jS, Y g:i a',
                'format_moment' => 'ddd MMM Do, YYYY h:mm:ss a',
                'label' => 'Mon March 10th, 2013 6:15 pm'
            ],
            [
                'format' => 'Y-m-d g:i a',
                'format_moment' => 'YYYY-MMM-DD h:mm:ss a',
                'label' => '2013-03-10 6:15 pm'
            ],
            [
                'format' => 'd-m-Y g:i a',
                'format_moment' => 'DD-MM-YYYY h:mm:ss a',
                'label' => '20-03-2013 6:15 pm'
            ],
            [
                'format' => 'm/d/Y g:i a',
                'format_moment' => 'MM/DD/YYYY h:mm:ss a',
                'label' => '03/20/2013 6:15 pm'
            ]
        ];
        
        foreach ($formats as $format) {
            $record = DatetimeFormat::whereLabel($format['label'])->first();
            if ($record) {
                $record->format = $format['format'];
                $record->format_moment = $format['format_moment'];
                $record->save();
            } else {
                DatetimeFormat::create($format);
            }
        }
    }
}
