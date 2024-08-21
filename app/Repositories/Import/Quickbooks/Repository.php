<?php

namespace App\Repositories\Import\Quickbooks;

use Illuminate\Support\Collection;
use App\Repositories\Import\Quickbooks\Contracts\RepositoryInterface;
use App\Services\Import\Quickbooks\Contracts\SdkInterface as QuickbooksInterface;
use App\Repositories\Import\Quickbooks\Transformers\Transformer as QuickbooksTransformer;

abstract class Repository implements RepositoryInterface
{
    
    protected string $entity;
    protected QuickbooksInterface $db;
    protected QuickbooksTransformer $transfomer;

    public function __construct(QuickbooksInterface $db, QuickbooksTransformer $transfomer)
    {
        $this->db= $db;
        $this->transformer = $transfomer;
    }

    public function count() : int {
        return $this->db->totalRecords($this->entity);
    }

    public function all() : Collection
    {
        return $this->get($this->count());
    }

    public function get(int $max = 100): Collection
    {
        return $this->transformer->transform($this->db->fetchRecords($this->entity, $max), $this->entity);
    }

    
}