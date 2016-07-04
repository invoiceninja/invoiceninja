<?php namespace App\Console\Commands;

use DateTime;
use App\Models\Document;
use Illuminate\Console\Command;

/**
 * Class RemoveOrphanedDocuments
 */
class RemoveOrphanedDocuments extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:remove-orphaned-documents';
    /**
     * @var string
     */
    protected $description = 'Removes old documents not associated with an expense or invoice';
    
    public function fire()
    {
        $this->info(date('Y-m-d').' Running RemoveOrphanedDocuments...');

        $documents = Document::whereRaw('invoice_id IS NULL AND expense_id IS NULL AND updated_at <= ?', [new DateTime('-1 hour')])
            ->get();
        
        $this->info(count($documents).' orphaned document(s) found');

        foreach ($documents as $document) {
            $document->delete();
        }

        $this->info('Done');
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
