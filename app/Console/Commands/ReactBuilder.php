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
use Illuminate\Support\Facades\Storage;
use stdClass;

class ReactBuilder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:react';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Builds blade component for react includes';

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
        $includes = '';

        $directoryIterator = new \RecursiveDirectoryIterator(public_path('react'), \RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
            if (str_contains($file->getFileName(), '.js')) {
                if (str_contains($file->getFileName(), 'index.')) {
                    $includes .= '<script type="module" crossorigin src="/react/'.$file->getFileName().'"></script>'."\n";
                } else {
                    $includes .= '<link rel="modulepreload" href="/react/'.$file->getFileName().'">'."\n";
                }
            }

            if (str_contains($file->getFileName(), '.css')) {
                $includes .= '<link rel="stylesheet" href="/react/'.$file->getFileName().'">'."\n";
            }
        }

        file_put_contents(resource_path('views/react/head.blade.php'), $includes);
    }
}
