<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use App\Libraries\MultiDB;
use App\Models\Backup;
use App\Models\Design;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use stdClass;

class TranslationsExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:translations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transform translations to json';

    private array $langs = [
        'ar',
        'ca',
        'cs',
        'da',
        'de',
        'el',
        'en',
        'en_GB',
        'es',
        'es_ES',
        'fa',
        'fi',
        'fr',
        'fr_CA',
        'hr',
        'it',
        'ja',
        'lt',
        'lv_LV',
        'mk_MK',
        'nb_NO',
        'nl',
        'pl',
        'pt_BR',
        'pt_PT',
        'ro',
        'ru_RU',
        'sl',
        'sq',
        'sv',
        'th',
        'tr_TR',
        'zh_TW',
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Storage::makeDirectory(storage_path('lang'));

        foreach ($this->langs as $lang) {
            Storage::makeDirectory(storage_path("lang/{$lang}"));

            $translations = Lang::getLoader()->load($lang, 'texts');

            Storage::put(storage_path("lang/{$lang}/{$lang}.json"), json_encode(Arr::dot($translations), JSON_UNESCAPED_UNICODE));
        }
    }
}
