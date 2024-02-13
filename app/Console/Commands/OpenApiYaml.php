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

    private array $directories = [
        '/components/schemas',
        '/paths/'
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
        $path = base_path('openapi');

        $directory = new DirectoryIterator($path);

        $this->info($directory);

        foreach ($directory as $file) {
            $this->info($file);
        }

        Storage::disk('base')->delete('/openapi/api-docs.yaml');
        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/info.yaml'));
        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/paths.yaml'));

        //iterate paths
        $directory = new DirectoryIterator($path . '/paths/');

        foreach ($directory as $file) {
            if ($file->isFile() && ! $file->isDot()) {
                Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents("{$path}/paths/{$file->getFilename()}"));
            }
        }

        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/components.yaml'));

        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/components/examples.yaml'));

        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/components/responses.yaml'));

        $directory = new DirectoryIterator($path . '/components/responses/');

        foreach ($directory as $file) {
            if ($file->isFile() && ! $file->isDot()) {
                Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents("{$path}/components/responses/{$file->getFilename()}"));
            }
        }

        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/components/parameters.yaml'));

        $directory = new DirectoryIterator($path . '/components/parameters/');

        foreach ($directory as $file) {
            if ($file->isFile() && ! $file->isDot()) {
                Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents("{$path}/components/parameters/{$file->getFilename()}"));
            }
        }

        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/components/schemas.yaml'));

        //iterate schemas

        $directory = new DirectoryIterator($path . '/components/schemas/');

        foreach ($directory as $file) {
            if ($file->isFile() && ! $file->isDot()) {
                Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents("{$path}/components/schemas/{$file->getFilename()}"));
            }
        }


        // Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/components/schemas/account.yaml'));
        Storage::disk('base')->append('/openapi/api-docs.yaml', file_get_contents($path.'/misc/misc.yaml'));
    }
}
