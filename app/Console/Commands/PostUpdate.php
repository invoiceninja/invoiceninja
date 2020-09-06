<?php

namespace App\Console\Commands;

use Composer\Composer;
use Composer\Console\Application;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\NullIO;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;

class PostUpdate extends Command
{
    protected $name = 'ninja:post-update';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:post-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run basic upgrade commands';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        set_time_limit(0);

        info('running post update');

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Exception $e) {
            \Log::error("I wasn't able to migrate the data.");
        }

        try {
            Artisan::call('optimize');
        } catch (Exception $e) {
            \Log::error("I wasn't able to optimize.");
        }

        $composer_data = [
          'url' => 'https://getcomposer.org/composer.phar',
          'dir' => __DIR__.'/.code',
          'bin' => __DIR__.'/.code/composer.phar',
          'json' => __DIR__.'/.code/composer.json',
          'conf' => [
            'autoload' => [
              'psr-4' => [
                '' => 'local/',
              ],
            ],
          ],
        ];

        if (! is_dir($composer_data['dir'])) {
            mkdir($composer_data['dir'], 0777, true);
        }

        if (! is_dir("{$composer_data['dir']}/local")) {
            mkdir("{$composer_data['dir']}/local", 0777, true);
        }

        copy($composer_data['url'], $composer_data['bin']);
        require_once "phar://{$composer_data['bin']}/src/bootstrap.php";

        $conf_json = json_encode($composer_data['conf'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($composer_data['json'], $conf_json);
        chdir($composer_data['dir']);
        putenv("COMPOSER_HOME={$composer_data['dir']}");
        putenv('OSTYPE=OS400');
        $app = new \Composer\Console\Application();

        $factory = new \Composer\Factory();
        $output = $factory->createOutput();

        $input = new \Symfony\Component\Console\Input\ArrayInput([
          'command' => 'install',
        ]);
        $input->setInteractive(false);
        echo '<pre>';
        $cmdret = $app->doRun($input, $output);
        echo 'end!';

        \Log::error(print_r($cmdret, 1));
    }
}
