<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FilterController extends BaseController
{
    private array $base_filters = ['archive', 'restore', 'delete'];

    public function __construct()
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index(Request $request, string $entity)
    {
        $entity_filters = [];

        switch ($entity) {

            case 'invoice':
                $entity_filters = ['bulk_download', 'mark_paid', 'mark_sent', 'download', 'cancel', 'email'];
                break;

            case 'quote':
                $entity_filters = ['bulk_download', 'convert', 'convert_to_invoice', 'download', 'approve', 'email', 'mark_sent'];
                break;

            case 'credit':
                $entity_filters = ['bulk_download', 'download', 'email', 'mark_sent'];
                break;

            case 'payment':
                $entity_filters = ['bulk_download', 'download', 'email', 'email_receipt'];
                break;

            case 'recurring_invoice':
                $entity_filters = ['bulk_download', 'start', 'stop', 'email'];
                break;

        }

        return response()->json(array_merge($this->base_filters, $entity_filters), 200);
    }
}
