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

use App\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        // https://github.com/caouecs/Laravel-lang
        // https://www.loc.gov/standards/iso639-2/php/code_list.php

        $languages = [
            ['id' => 1, 'name' => 'English - United States', 'locale' => 'en'],
            ['id' => 2, 'name' => 'Italian', 'locale' => 'it'],
            ['id' => 3, 'name' => 'German', 'locale' => 'de'],
            ['id' => 4, 'name' => 'French', 'locale' => 'fr'],
            ['id' => 5, 'name' => 'Portuguese - Brazilian', 'locale' => 'pt_BR'],
            ['id' => 6, 'name' => 'Dutch', 'locale' => 'nl'],
            ['id' => 7, 'name' => 'Spanish', 'locale' => 'es'],
            ['id' => 8, 'name' => 'Norwegian', 'locale' => 'nb_NO'],
            ['id' => 9, 'name' => 'Danish', 'locale' => 'da'],
            ['id' => 10, 'name' => 'Japanese', 'locale' => 'ja'],
            ['id' => 11, 'name' => 'Swedish', 'locale' => 'sv'],
            ['id' => 12, 'name' => 'Spanish - Spain', 'locale' => 'es_ES'],
            ['id' => 13, 'name' => 'French - Canada', 'locale' => 'fr_CA'],
            ['id' => 14, 'name' => 'Lithuanian', 'locale' => 'lt'],
            ['id' => 15, 'name' => 'Polish', 'locale' => 'pl'],
            ['id' => 16, 'name' => 'Czech', 'locale' => 'cs'],
            ['id' => 17, 'name' => 'Croatian', 'locale' => 'hr'],
            ['id' => 18, 'name' => 'Albanian', 'locale' => 'sq'],
            ['id' => 19, 'name' => 'Greek', 'locale' => 'el'],
            ['id' => 20, 'name' => 'English - United Kingdom', 'locale' => 'en_GB'],
            ['id' => 21, 'name' => 'Portuguese - Portugal', 'locale' => 'pt_PT'],
            ['id' => 22, 'name' => 'Slovenian', 'locale' => 'sl'],
            ['id' => 23, 'name' => 'Finnish', 'locale' => 'fi'],
            ['id' => 24, 'name' => 'Romanian', 'locale' => 'ro'],
            ['id' => 25, 'name' => 'Turkish - Turkey', 'locale' => 'tr_TR'],
            ['id' => 26, 'name' => 'Thai', 'locale' => 'th'],
            ['id' => 27, 'name' => 'Macedonian', 'locale' => 'mk_MK'],
            ['id' => 28, 'name' => 'Chinese - Taiwan', 'locale' => 'zh_TW'],
            ['id' => 29, 'name' => 'Russian (Russia)', 'locale' => 'ru_RU'],
            ['id' => 30, 'name' => 'Arabic', 'locale' => 'ar'],
            ['id' => 31, 'name' => 'Persian', 'locale' => 'fa'],
            ['id' => 32, 'name' => 'Latvian', 'locale' => 'lv_LV'],
        ];

        foreach ($languages as $language) {
            $record = Language::whereLocale($language['locale'])->first();
            if ($record) {
                $record->name = $language['name'];
                $record->save();
            } else {
                Language::create($language);
            }
        }
    }
}
