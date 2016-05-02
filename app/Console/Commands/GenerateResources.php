<?php namespace App\Console\Commands;

use File;
use Illuminate\Console\Command;

class GenerateResources extends Command
{
    protected $name = 'ninja:generate-resources';
    protected $description = 'Generate Resouces';

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
     * Execute the command.
     *
     * @return void
     */
    public function fire()
    {
        $langs = [
            'da',
            'de',
            'en',
            'es',
            'es_ES',
            'fr',
            'fr_CA',
            'it',
            'lt',
            'nb_NO',
            'nl',
            'pt_BR',
            'sv'
        ];

        $texts = File::getRequire(base_path() . '/resources/lang/en/texts.php');

        foreach ($texts as $key => $value) {
            if (is_array($value)) {
                echo $key;
            } else {
                echo "$key => $value\n";
            }
        }
    }

    protected function getArguments()
    {
        return array(
            //array('example', InputArgument::REQUIRED, 'An example argument.'),
        );
    }

    protected function getOptions()
    {
        return array(
            //array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
        );
    }
}
