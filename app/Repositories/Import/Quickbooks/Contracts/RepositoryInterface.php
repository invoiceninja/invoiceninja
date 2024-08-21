<?php

namespace App\Repositories\Import\Quickbooks\Contracts;

use Illuminate\Support\Collection;

interface RepositoryInterface {

   function get(int $max = 100): Collection;
   function all(): Collection;
   function count(): int;
}