<?php

namespace App\Http\ViewComponents;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\View;
use App\Ninja\Repositories\ClientRepository;

class SimpleSelectComponent implements Htmlable
{
    protected $entityType;
    protected $items;
    protected $itemLabel;
    protected $label;
    protected $module;
    protected $secondaryItemLabel;
    protected $selectId;

    public function __construct($entityType, $items, $itemLabel, $label, $secondaryItemLabel = null, $module = null, $selectId = null) {
        $this->entityType = $entityType;
        $this->items = $items;
        $this->itemLabel = $itemLabel;
        $this->label = $label;
        $this->module = $module;
        $this->secondaryItemLabel = $secondaryItemLabel;

        if ($selectId) {
            $this->selectId = $selectId;
        } else {
            $this->selectId = $label . '_id';
        }
    }

    public function toHtml()
    {
        return View::make('components.simple_select')->with(['entityType' => $this->entityType, 'items' => $this->items, 'itemLabel' => $this->itemLabel, 'secondaryItemLabel' => $this->secondaryItemLabel, 'label' => mtrans($this->module, $this->label), 'selectId' => $this->selectId])->render();
    }
}