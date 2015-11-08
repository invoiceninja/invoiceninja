<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/6/15
 * Time: 10:36
 */

namespace app\Ninja\Import\FreshBooks;

use App\Ninja\Import\FreshBooks\ImporterInterface;
use App\Ninja\Import\FreshBooks\Importer;

class FreshBooksImporter implements ImporterInterface
{
    private $importer;

    public function __construct(Importer $importer)
    {
        $this->importer = $importer;
    }

    /**
     * @param $files - List of CSV files exported from Freshbook
     * @return null|string - Which files were actually imported
     */
    public function execute($files)
    {
        $imported_files = null;

        foreach($files as $entity => $file)
        {
            $imported_files = $imported_files . $this->importer->execute($entity, $file);
        }
        return $imported_files;
    }
}