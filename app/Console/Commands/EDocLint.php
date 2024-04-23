<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\DataProviders\FatturaPADataProvider;

class EDocLint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:edoclint';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Builds json files component data maps';

    private array $classes = [
        FatturaPADataProvider::class,
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
     * @return void
     */
    public function handle()
    {
        foreach($this->classes as $class)
        {

            $provider = new $class();

            foreach($provider as $key => $value) {

                $json = json_encode($provider->{$key}, JSON_PRETTY_PRINT);
                Storage::disk('local')->put($key.'.json', $json);

            }

        }

    }
}
