<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/7/15
 * Time: 08:31
 */
namespace App\Ninja\Import\FreshBooks;

/**
 * Interface ImporterInterface
 * @package app\Ninja\Interfaces
 */
interface ImporterInterface
{
    public function execute($stream);
}