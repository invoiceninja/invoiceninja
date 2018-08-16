<?php

namespace App\Http\ViewComponents;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\View;
use App\Ninja\Repositories\ClientRepository;

class ClientSelectSimpleComponent implements Htmlable
{
    public function __construct() {

    }

    public function toHtml()
    {
        // dd($this->clients->all());
        return View::make('components.client_select_simple')->render();
    }
}