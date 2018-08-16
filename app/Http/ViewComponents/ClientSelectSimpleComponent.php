<?php

namespace App\Http\ViewComponents;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\View;
use App\Ninja\Repositories\ClientRepository;

class ClientSelectSimpleComponent implements Htmlable
{
    protected $clients;
    protected $label;
    protected $module;
    protected $selectId;

    public function __construct($clients, $label = ENTITY_CLIENT, $module = null, $selectId = null) {
        $this->clients = $clients;
        $this->label = $label;
        $this->module = $module;

        if ($selectId) {
            $this->selectId = $selectId;
        } else {
            $this->selectId = $label . '_id';
        }
    }

    public function toHtml()
    {
        return View::make('components.client_select_simple')->with(['clients' => $this->clients,  'label' => mtrans($this->module, $this->label), 'selectId' => $this->selectId])->render();
    }
}