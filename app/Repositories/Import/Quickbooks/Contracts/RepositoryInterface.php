<?php

namespace App\Repositories\Import\Quickbooks\Contracts;

use Illuminate\Support\Collection;

interface RepositoryInterface
{
    public function get(int $max = 100): Collection;
    public function all(): Collection;
    public function count(): int;
}
