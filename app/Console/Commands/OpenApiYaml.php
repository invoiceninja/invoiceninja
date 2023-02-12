<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use DirectoryIterator;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OpenApiYaml extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:openapi';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build OpenApi YAML';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->faker = Factory::create();

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $path = base_path('openapi');

        $directory = new DirectoryIterator($path);

        $this->info($directory);

        foreach ($directory as $file) {

        $this->info($file);

        Storage::disk('base')->delete('/openapi/api-docs.yaml');
        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/info.yaml'));
        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/paths/paths.yaml'));
        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/components/components.yaml'));
        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/misc/misc.yaml'));


        }
    }

}
