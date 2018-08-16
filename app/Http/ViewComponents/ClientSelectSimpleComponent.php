<?php

namespace App\Http\ViewComponents;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\View;
use App\Ninja\Repositories\ClientRepository;

class ClientSelectSimpleComponent implements Htmlable
{
    protected $clients;
    protected $selectId;

    public function __construct($clients, $selectId) {
        $this->clients = $clients;
        $this->selectId = $selectId;
    }

    public function toHtml()
    {
        // dd($this->clients->all());
        return View::make('components.client_select_simple')->with(['clients' => $this->clients, 'selectId' => $this->selectId])->render();
    }
}