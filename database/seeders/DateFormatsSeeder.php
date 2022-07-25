<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Database\Seeders;

use App\Models\DateFormat;
use App\Models\DatetimeFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DateFormatsSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        // Date formats
        $formats = [
            ['id' => 1, 'format' => 'd/M/Y', 'format_moment' => 'DD/MMM/YYYY', 'format_dart' => 'dd/MMM/yyyy'],
            ['id' => 2, 'format' => 'd-M-Y',  'format_moment' => 'DD-MMM-YYYY', 'format_dart' => 'dd-MMM-yyyy'],
            ['id' => 3, 'format' => 'd/F/Y',  'format_moment' => 'DD/MMMM/YYYY', 'format_dart' => 'dd/MMMM/yyyy'],
            ['id' => 4, 'format' => 'd-F-Y',  'format_moment' => 'DD-MMMM-YYYY', 'format_dart' => 'dd-MMMM-yyyy'],
            ['id' => 5, 'format' => 'M j, Y',  'format_moment' => 'MMM D, YYYY', 'format_dart' => 'MMM d, yyyy'],
            ['id' => 6, 'format' => 'F j, Y',  'format_moment' => 'MMMM D, YYYY', 'format_dart' => 'MMMM d, yyyy'],
            ['id' => 7, 'format' => 'D M j, Y',  'format_moment' => 'ddd MMM D, YYYY', 'format_dart' => 'EEE MMM d, yyyy'],
            ['id' => 8, 'format' => 'Y-m-d',  'format_moment' => 'YYYY-MM-DD', 'format_dart' => 'yyyy-MM-dd'],
            ['id' => 9, 'format' => 'd-m-Y',  'format_moment' => 'DD-MM-YYYY', 'format_dart' => 'dd-MM-yyyy'],
            ['id' => 10, 'format' => 'm/d/Y',  'format_moment' => 'MM/DD/YYYY', 'format_dart' => 'MM/dd/yyyy'],
            ['id' => 11, 'format' => 'd.m.Y',  'format_moment' => 'D.MM.YYYY', 'format_dart' => 'dd.MM.yyyy'],
            ['id' => 12, 'format' => 'j. M. Y',  'format_moment' => 'DD. MMM. YYYY', 'format_dart' => 'd. MMM. yyyy'],
            ['id' => 13, 'format' => 'j. F Y',  'format_moment' => 'DD. MMMM YYYY', 'format_dart' => 'd. MMMM yyyy'],
            ['id' => 14, 'format' => 'd/m/Y',  'format_moment' => 'DD/MM/YYYY', 'format_dart' => 'dd/MM/yyyy'],
        ];

        foreach ($formats as $format) {
            // use binary to support case-sensitive search
            $record = DateFormat::whereRaw('BINARY `format`= ?', [$format['format']])->first();
            if ($record) {
                $record->format_moment = $format['format_moment'];
                $record->format_dart = $format['format_dart'];
                $record->save();
            } else {
                DateFormat::create($format);
            }
        }

        // Date/time formats
        $formats = [
            ['id' => 1, 'format' => 'd/M/Y g:i a', 'format_moment' => 'DD/MMM/YYYY h:mm:ss a', 'format_dart' => 'dd/MMM/yyyy h:mm a'],
            ['id' => 2, 'format' => 'd-M-Y g:i a', 'format_moment' => 'DD-MMM-YYYY h:mm:ss a', 'format_dart' => 'dd-MMM-yyyy h:mm a'],
            ['id' => 3, 'format' => 'd/F/Y g:i a', 'format_moment' => 'DD/MMMM/YYYY h:mm:ss a', 'format_dart' => 'dd/MMMM/yyyy h:mm a'],
            ['id' => 4, 'format' => 'd-F-Y g:i a', 'format_moment' => 'DD-MMMM-YYYY h:mm:ss a', 'format_dart' => 'dd-MMMM-yyyy h:mm a'],
            ['id' => 5, 'format' => 'M j, Y g:i a', 'format_moment' => 'MMM D, YYYY h:mm:ss a', 'format_dart' => 'MMM d, yyyy h:mm a'],
            ['id' => 6, 'format' => 'F j, Y g:i a', 'format_moment' => 'MMMM D, YYYY h:mm:ss a', 'format_dart' => 'MMMM d, yyyy h:mm a'],
            ['id' => 7, 'format' => 'D M jS, Y g:i a', 'format_moment' => 'ddd MMM Do, YYYY h:mm:ss a', 'format_dart' => 'EEE MMM d, yyyy h:mm a'],
            ['id' => 8, 'format' => 'Y-m-d g:i a', 'format_moment' => 'YYYY-MM-DD h:mm:ss a', 'format_dart' => 'yyyy-MM-dd h:mm a'],
            ['id' => 9, 'format' => 'd-m-Y g:i a', 'format_moment' => 'DD-MM-YYYY h:mm:ss a', 'format_dart' => 'dd-MM-yyyy h:mm a'],
            ['id' => 10, 'format' => 'm/d/Y g:i a', 'format_moment' => 'MM/DD/YYYY h:mm:ss a', 'format_dart' => 'MM/dd/yyyy h:mm a'],
            ['id' => 11, 'format' => 'd.m.Y g:i a', 'format_moment' => 'D.MM.YYYY h:mm:ss a', 'format_dart' => 'dd.MM.yyyy h:mm a'],
            ['id' => 12, 'format' => 'j. M. Y g:i a', 'format_moment' => 'DD. MMM. YYYY h:mm:ss a', 'format_dart' => 'd. MMM. yyyy h:mm a'],
            ['id' => 13, 'format' => 'j. F Y g:i a', 'format_moment' => 'DD. MMMM YYYY h:mm:ss a', 'format_dart' => 'd. MMMM yyyy h:mm a'],
            ['id' => 14, 'format' => 'd/m/Y g:i a', 'format_moment' => 'DD/MM/YYYY h:mm:ss a', 'format_dart' => 'dd/MM/yyyy h:mm a'],
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
