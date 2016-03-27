<?php namespace App\Console\Commands;

use DateTime;
use App\Models\Document;
use Illuminate\Console\Command;

class RemoveOrphanedDocuments extends Command
{
    protected $name = 'ninja:remove-orphaned-documents';
    protected $description = 'Removes old documents not associated with an expense or invoice';
    
    public function fire()
    {
        $this->info(date('Y-m-d').' Running RemoveOrphanedDocuments...');

        $documents = Document::whereRaw('invoice_id IS NULL AND expense_id IS NULL AND updated_at <= ?', array(new DateTime('-1 hour')))
            ->get();
        
        $this->info(count($documents).' orphaned document(s) found');

        foreach ($documents as $document) {
            $document->delete();
        }

        $this->info('Done');
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
