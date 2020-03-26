<?php

use App\Models\Language;

class LanguageSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        // https://github.com/caouecs/Laravel-lang
        // https://www.loc.gov/standards/iso639-2/php/code_list.php

        $languages = [
            ['name' => 'English', 'locale' => 'en'],
            ['name' => 'Italian', 'locale' => 'it'],
            ['name' => 'German', 'locale' => 'de'],
            ['name' => 'French', 'locale' => 'fr'],
            ['name' => 'Portuguese - Brazilian', 'locale' => 'pt_BR'],
            ['name' => 'Dutch', 'locale' => 'nl'],
            ['name' => 'Spanish', 'locale' => 'es'],
            ['name' => 'Norwegian', 'locale' => 'nb_NO'],
            ['name' => 'Danish', 'locale' => 'da'],
            ['name' => 'Japanese', 'locale' => 'ja'],
            ['name' => 'Swedish', 'locale' => 'sv'],
            ['name' => 'Spanish - Spain', 'locale' => 'es_ES'],
            ['name' => 'French - Canada', 'locale' => 'fr_CA'],
            ['name' => 'Lithuanian', 'locale' => 'lt'],
            ['name' => 'Polish', 'locale' => 'pl'],
            ['name' => 'Czech', 'locale' => 'cs'],
            ['name' => 'Croatian', 'locale' => 'hr'],
            ['name' => 'Albanian', 'locale' => 'sq'],
            ['name' => 'Greek', 'locale' => 'el'],
            ['name' => 'English - United Kingdom', 'locale' => 'en_GB'],
            ['name' => 'Portuguese - Portugal', 'locale' => 'pt_PT'],
            ['name' => 'Slovenian', 'locale' => 'sl'],
            ['name' => 'Finnish', 'locale' => 'fi'],
            ['name' => 'Romanian', 'locale' => 'ro'],
            ['name' => 'Turkish - Turkey', 'locale' => 'tr_TR'],
            ['name' => 'Thai', 'locale' => 'th'],
            ['name' => 'Macedonian', 'locale' => 'mk_MK'],
            ['name' => 'Chinese - Taiwan', 'locale' => 'zh_TW'],
            ['name' => 'English - Australia', 'locale' => 'en_AU'],
            ['name' => 'Serbian', 'locale' => 'sr_RS'],
            ['name' => 'Bulgarian', 'locale' => 'bg'],
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

        Eloquent::reguard();
    }
}
