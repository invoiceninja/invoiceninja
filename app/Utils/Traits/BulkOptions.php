<?php


namespace App\Utils\Traits;


trait BulkOptions
{
    /**
     * Available bulk options - used in requests (eg. BulkClientRequests)
     *
     * @return array
     * @var array
     */
    public function getBulkOptions()
    {
        return [
            'create', 'edit', 'view',
        ];
    }

    /**
     * Shared rules for bulk requests.
     *
     * @return array
     * @var array
     */
    public function getGlobalRules()
    {
        return [
            'action' => ['required'],
        ];
    }
}
