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
            ['format' => 'd/M/Y', 'picker_format' => 'dd/M/yyyy', 'format_moment' => 'DD/MMM/YYYY', 'format_dart' => 'dd/MMM/yyyy'],
            ['format' => 'd-M-Y', 'picker_format' => 'dd-M-yyyy', 'format_moment' => 'DD-MMM-YYYY', 'format_dart' => 'dd-MMM-yyyy'],
            ['format' => 'd/F/Y', 'picker_format' => 'dd/MM/yyyy', 'format_moment' => 'DD/MMMM/YYYY', 'format_dart' => 'dd/MMMM/yyyy'],
            ['format' => 'd-F-Y', 'picker_format' => 'dd-MM-yyyy', 'format_moment' => 'DD-MMMM-YYYY', 'format_dart' => 'dd-MMMM-yyyy'],
            ['format' => 'M j, Y', 'picker_format' => 'M d, yyyy', 'format_moment' => 'MMM D, YYYY', 'format_dart' => 'MMM d, yyyy'],
            ['format' => 'F j, Y', 'picker_format' => 'MM d, yyyy', 'format_moment' => 'MMMM D, YYYY', 'format_dart' => 'MMMM d, yyyy'],
            ['format' => 'D M j, Y', 'picker_format' => 'D MM d, yyyy', 'format_moment' => 'ddd MMM Do, YYYY', 'format_dart' => 'EEE MMM d, yyyy'],
            ['format' => 'Y-m-d', 'picker_format' => 'yyyy-mm-dd', 'format_moment' => 'YYYY-MM-DD', 'format_dart' => 'yyyy-MM-dd'],
            ['format' => 'd-m-Y', 'picker_format' => 'dd-mm-yyyy', 'format_moment' => 'DD-MM-YYYY', 'format_dart' => 'dd-MM-yyyy'],
            ['format' => 'm/d/Y', 'picker_format' => 'mm/dd/yyyy', 'format_moment' => 'MM/DD/YYYY', 'format_dart' => 'MM/dd/yyyy'],
            ['format' => 'd.m.Y', 'picker_format' => 'dd.mm.yyyy', 'format_moment' => 'D.MM.YYYY', 'format_dart' => 'dd.MM.yyyy'],
            ['format' => 'j. M. Y', 'picker_format' => 'd. M. yyyy', 'format_moment' => 'DD. MMM. YYYY', 'format_dart' => 'd. MMM. yyyy'],
            ['format' => 'j. F Y', 'picker_format' => 'd. MM yyyy', 'format_moment' => 'DD. MMMM YYYY', 'format_dart' => 'd. MMMM yyyy'],
        ];

        foreach ($formats as $format) {
            // use binary to support case-sensitive search
            $record = DateFormat::whereRaw('BINARY `format`= ?', [$format['format']])->first();
            if ($record) {
                $record->picker_format = $format['picker_format'];
                $record->format_moment = $format['format_moment'];
                $record->format_dart = $format['format_dart'];
                $record->save();
            } else {
                DateFormat::create($format);
            }
        }

        // Date/time formats
        $formats = [
            ['format' => 'd/M/Y g:i a', 'format_moment' => 'DD/MMM/YYYY h:mm:ss a', 'format_dart' => 'dd/MMM/yyyy h:mm a'],
            ['format' => 'd-M-Y g:i a', 'format_moment' => 'DD-MMM-YYYY h:mm:ss a', 'format_dart' => 'dd-MMM-yyyy h:mm a'],
            ['format' => 'd/F/Y g:i a', 'format_moment' => 'DD/MMMM/YYYY h:mm:ss a', 'format_dart' => 'dd/MMMM/yyyy h:mm a'],
            ['format' => 'd-F-Y g:i a', 'format_moment' => 'DD-MMMM-YYYY h:mm:ss a', 'format_dart' => 'dd-MMMM-yyyy h:mm a'],
            ['format' => 'M j, Y g:i a', 'format_moment' => 'MMM D, YYYY h:mm:ss a', 'format_dart' => 'MMM d, yyyy h:mm a'],
            ['format' => 'F j, Y g:i a', 'format_moment' => 'MMMM D, YYYY h:mm:ss a', 'format_dart' => 'MMMM d, yyyy h:mm a'],
            ['format' => 'D M jS, Y g:i a', 'format_moment' => 'ddd MMM Do, YYYY h:mm:ss a', 'format_dart' => 'EEE MMM d, yyyy h:mm a'],
            ['format' => 'Y-m-d g:i a', 'format_moment' => 'YYYY-MM-DD h:mm:ss a', 'format_dart' => 'yyyy-MM-dd h:mm a'],
            ['format' => 'd-m-Y g:i a', 'format_moment' => 'DD-MM-YYYY h:mm:ss a', 'format_dart' => 'dd-MM-yyyy h:mm a'],
            ['format' => 'm/d/Y g:i a', 'format_moment' => 'MM/DD/YYYY h:mm:ss a', 'format_dart' => 'MM/dd/yyyy h:mm a'],
            ['format' => 'd.m.Y g:i a', 'format_moment' => 'D.MM.YYYY h:mm:ss a', 'format_dart' => 'dd.MM.yyyy h:mm a'],
            ['format' => 'j. M. Y g:i a', 'format_moment' => 'DD. MMM. YYYY h:mm:ss a', 'format_dart' => 'd. MMM. yyyy h:mm a'],
            ['format' => 'j. F Y g:i a', 'format_moment' => 'DD. MMMM YYYY h:mm:ss a', 'format_dart' => 'd. MMMM yyyy h:mm a'],
        ];

        foreach ($formats as $format) {
            $record = DatetimeFormat::whereRaw('BINARY `format`= ?', [$format['format']])->first();
            if ($record) {
                $record->format_moment = $format['format_moment'];
                $record->format_dart = $format['format_dart'];
                $record->save();
            } else {
                DatetimeFormat::create($format);
            }
        }
    }
}
