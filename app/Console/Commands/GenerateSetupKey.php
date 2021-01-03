<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSetupKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:generate-setup-key';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate random APP_KEY value';

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
     * @return int
     */
    public function handle()
    {
        $randomString = base64_encode(\Illuminate\Support\Str::random(32));

        $this->info('Success! Copy the following content into your .env or docker-compose.yml:');
        $this->warn('base64:' . $randomString);
    }
}
