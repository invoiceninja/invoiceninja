<?php

namespace App\Import\Transformer\Quickbooks;

use Illuminate\Support\Arr;

trait CommonTrait
{
    protected $model;

    public function getString($data, $field)
    {
        return Arr::get($data, $field);
    }

    public function getCreateTime($data, $field = null)
    {
        return $this->parseDateOrNull($data, 'MetaData.CreateTime');
    }

    public function getLastUpdatedTime($data, $field = null)
    {
        return $this->parseDateOrNull($data, 'MetaData.LastUpdatedTime');
    }

    public function transform($data)
    {
        $transformed = [];

        foreach ($this->fillable as $key => $field) {
            $transformed[$key] = is_null((($v = $this->getString($data, $field)))) ? null : (method_exists($this, ($method = "get{$field}")) ? call_user_func([$this, $method], $data, $field) : $this->getString($data, $field));
        }

        return $this->model->fillable(array_keys($this->fillable))->fill($transformed)->toArray() + ['company_id' => $this->company->id ] ;
    }

}
