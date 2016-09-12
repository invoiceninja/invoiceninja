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
            ['format' => 'd/M/Y', 'picker_format' => 'dd/M/yyyy'],
            ['format' => 'd-M-Y', 'picker_format' => 'dd-M-yyyy'],
            ['format' => 'd/F/Y', 'picker_format' => 'dd/MM/yyyy'],
            ['format' => 'd-F-Y', 'picker_format' => 'dd-MM-yyyy'],
            ['format' => 'M j, Y', 'picker_format' => 'M d, yyyy'],
            ['format' => 'F j, Y', 'picker_format' => 'MM d, yyyy'],
            ['format' => 'D M j, Y', 'picker_format' => 'D MM d, yyyy'],
            ['format' => 'Y-m-d', 'picker_format' => 'yyyy-mm-dd'],
            ['format' => 'd-m-Y', 'picker_format' => 'dd-mm-yyyy'],
            ['format' => 'm/d/Y', 'picker_format' => 'mm/dd/yyyy'],
            ['format' => 'd.m.Y', 'picker_format' => 'dd.mm.yyyy'],
            ['format' => 'j. M. Y', 'picker_format' => 'd. M. yyyy'],
            ['format' => 'j. F Y', 'picker_format' => 'd. MM yyyy']
        ];

        foreach ($formats as $format) {
            // use binary to support case-sensitive search
            $record = DateFormat::whereRaw("BINARY `format`= ?", array($format['format']))->first();
            if ($record) {
                $record->picker_format = $format['picker_format'];
                $record->save();
            } else {
                DateFormat::create($format);
            }
        }

        // Date/time formats
        $formats = [
            ['format' => 'd/M/Y g:i a', 'format_moment' => 'DD/MMM/YYYY h:mm:ss a'],
            ['format' => 'd-M-Y g:i a', 'format_moment' => 'DD-MMM-YYYY h:mm:ss a'],
            ['format' => 'd/F/Y g:i a', 'format_moment' => 'DD/MMMM/YYYY h:mm:ss a'],
            ['format' => 'd-F-Y g:i a', 'format_moment' => 'DD-MMMM-YYYY h:mm:ss a'],
            ['format' => 'M j, Y g:i a', 'format_moment' => 'MMM D, YYYY h:mm:ss a'],
            ['format' => 'F j, Y g:i a', 'format_moment' => 'MMMM D, YYYY h:mm:ss a'],
            ['format' => 'D M jS, Y g:i a', 'format_moment' => 'ddd MMM Do, YYYY h:mm:ss a'],
            ['format' => 'Y-m-d g:i a', 'format_moment' => 'YYYY-MM-DD h:mm:ss a'],
            ['format' => 'd-m-Y g:i a', 'format_moment' => 'DD-MM-YYYY h:mm:ss a'],
            ['format' => 'm/d/Y g:i a', 'format_moment' => 'MM/DD/YYYY h:mm:ss a'],
            ['format' => 'd.m.Y g:i a', 'format_moment' => 'D.MM.YYYY h:mm:ss a'],
            ['format' => 'j. M. Y g:i a', 'format_moment' => 'DD. MMM. YYYY h:mm:ss a'],
            ['format' => 'j. F Y g:i a', 'format_moment' => 'DD. MMMM YYYY h:mm:ss a']
        ];

        foreach ($formats as $format) {
            $record = DatetimeFormat::whereRaw("BINARY `format`= ?", array($format['format']))->first();
            if ($record) {
                $record->format_moment = $format['format_moment'];
                $record->save();
            } else {
                DatetimeFormat::create($format);
            }
        }
    }
}
