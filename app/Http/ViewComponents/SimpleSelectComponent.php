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
    protected $fieldLabel;
    protected $module;
    protected $secondaryItemLabel;
    protected $selectId;
    protected $defaultValue;

    public function __construct($entityType, $items, $itemLabel, $fieldLabel, $defaultValue = null, $secondaryItemLabel = null, $module = null, $selectId = null) {
        $this->entityType = $entityType;
        $this->items = $items;
        $this->itemLabel = $itemLabel;
        $this->fieldLabel = $fieldLabel;
        $this->defaultValue = $defaultValue;
        $this->module = $module;
        $this->secondaryItemLabel = $secondaryItemLabel;

        if ($selectId) {
            $this->selectId = $selectId;
        } else {
            $this->selectId = $fieldLabel . '_id';
        }
    }

    public function toHtml()
    {
        return View::make('components.simple_select')->with([
            'entityType' => $this->entityType,
            'items' => $this->items,
            'itemLabel' => $this->itemLabel,
            'secondaryItemLabel' => $this->secondaryItemLabel,
            'fieldLabel' => mtrans($this->module, $this->fieldLabel),
            'selectId' => $this->selectId,
            'defaultValue' => $this->defaultValue,
        ])->render();
    }
}