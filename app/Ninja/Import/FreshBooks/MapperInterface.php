<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/8/15
 * Time: 10:35
 */

namespace app\Ninja\Import\FreshBooks;


interface MapperInterface
{
    public function validateHeader($csvHeader);
    public function getResourceMapper($data);
}