<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/9/15
 * Time: 11:03
 */

namespace App\Ninja\Import;


interface DataImporterServiceInterface
{
    public function import($files);
}