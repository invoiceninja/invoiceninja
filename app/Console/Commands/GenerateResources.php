<?php namespace App\Console\Commands;

use File;
use Illuminate\Console\Command;

/**
 * Class GenerateResources
 */
class GenerateResources extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:generate-resources';
    /**
     * @var string
     */
    protected $description = 'Generate Resouces';

    /**
     * Create a new command instance.
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
        $texts = File::getRequire(base_path() . '/resources/lang/en/texts.php');

        foreach ($texts as $key => $value) {
            if (is_array($value)) {
                echo $key;
            } else {
                echo "$key => $value\n";
            }
        }
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
