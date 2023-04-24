<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;

class BaseExport
{
    use MakesHash;

    public array $input;

    public string $date_key = '';

    public array $entity_keys = [];

    public string $start_date = '';

    public string $end_date = '';

    public string $client_description = 'All Clients';

    protected function filterByClients($query)
    {
        if (isset($this->input['client_id']) && $this->input['client_id'] != 'all') {
            $client = Client::withTrashed()->find($this->input['client_id']);
            $this->client_description = $client->present()->name;
            return $query->where('client_id', $this->input['client_id']);
        }
        elseif(isset($this->input['clients']) && count($this->input['clients']) > 0) {

            $this->client_description = 'Multiple Clients';
            return $query->whereIn('client_id', $this->input['clients']);
        }
        return $query;
    }

    protected function addInvoiceStatusFilter($query, $status): Builder
    {

        $status_parameters = explode(',', $status);
        

        if(in_array('all', $status_parameters))
            return $query;

        $query->where(function ($nested) use ($status_parameters) {

            $invoice_filters = [];

            if (in_array('draft', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_DRAFT;
            }

            if (in_array('sent', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_SENT;
            }

            if (in_array('paid', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_PAID;
            }

            if (in_array('unpaid', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_SENT;
                $invoice_filters[] = Invoice::STATUS_PARTIAL;
            }

            if (count($invoice_filters) > 0) {
                $nested->whereIn('status_id', $invoice_filters);
            }
                                
            if (in_array('overdue', $status_parameters)) {
                $nested->orWhereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                ->where('due_date', '<', Carbon::now())
                                ->orWhere('partial_due_date', '<', Carbon::now());
            }

            if(in_array('viewed', $status_parameters)){
                
                $nested->whereHas('invitations', function ($q){
                    $q->whereNotNull('viewed_date')->whereNotNull('deleted_at');
                });

            }
                
            
        });

        return $query;
    }

    protected function addDateRange($query)
    {
        $date_range = $this->input['date_range'];

        if (array_key_exists('date_key', $this->input) && strlen($this->input['date_key']) > 1) {
            $this->date_key = $this->input['date_key'];
        }

        try {
            $custom_start_date = Carbon::parse($this->input['start_date']);
            $custom_end_date = Carbon::parse($this->input['end_date']);
        } catch (\Exception $e) {
            $custom_start_date = now()->startOfYear();
            $custom_end_date = now();
        }

        switch ($date_range) {
            case 'all':
                $this->start_date = 'All available data';
                $this->end_date = 'All available data';
                return $query;
            case 'last7':
                $this->start_date = now()->subDays(7)->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->subDays(7), now()])->orderBy($this->date_key, 'ASC');
            case 'last30':
                $this->start_date = now()->subDays(30)->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->subDays(30), now()])->orderBy($this->date_key, 'ASC');
            case 'this_month':
                $this->start_date = now()->startOfMonth()->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfMonth(), now()])->orderBy($this->date_key, 'ASC');
            case 'last_month':
                $this->start_date = now()->startOfMonth()->subMonth()->format('Y-m-d');
                $this->end_date = now()->startOfMonth()->subMonth()->endOfMonth()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfMonth()->subMonth(), now()->startOfMonth()->subMonth()->endOfMonth()])->orderBy($this->date_key, 'ASC');
            case 'this_quarter':
                $this->start_date = (new \Carbon\Carbon('-3 months'))->firstOfQuarter()->format('Y-m-d');
                $this->end_date = (new \Carbon\Carbon('-3 months'))->lastOfQuarter()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [(new \Carbon\Carbon('-3 months'))->firstOfQuarter(), (new \Carbon\Carbon('-3 months'))->lastOfQuarter()])->orderBy($this->date_key, 'ASC');
            case 'last_quarter':
                $this->start_date = (new \Carbon\Carbon('-6 months'))->firstOfQuarter()->format('Y-m-d');
                $this->end_date = (new \Carbon\Carbon('-6 months'))->lastOfQuarter()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [(new \Carbon\Carbon('-6 months'))->firstOfQuarter(), (new \Carbon\Carbon('-6 months'))->lastOfQuarter()])->orderBy($this->date_key, 'ASC');
            case 'last365_days':
                $this->start_date = now()->startOfDay()->subDays(365)->format('Y-m-d');
                $this->end_date = now()->startOfDay()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->subDays(365), now()])->orderBy($this->date_key, 'ASC');
            case 'this_year':
                $this->start_date = now()->startOfYear()->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfYear(), now()])->orderBy($this->date_key, 'ASC');
            case 'custom':
                $this->start_date = $custom_start_date->format('Y-m-d');
                $this->end_date = $custom_end_date->format('Y-m-d');
                return $query->whereBetween($this->date_key, [$custom_start_date, $custom_end_date])->orderBy($this->date_key, 'ASC');
            default:
                $this->start_date = now()->startOfYear()->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
                return $query->whereBetween($this->date_key, [now()->startOfYear(), now()])->orderBy($this->date_key, 'ASC');
        }
    }

    public function buildHeader() :array
    {
        $header = [];

        foreach ($this->input['report_keys'] as $value) {
            $key = array_search($value, $this->entity_keys);

            $key = str_replace('item.', '', $key);
            $key = str_replace('invoice.', '', $key);
            $key = str_replace('client.', '', $key);
            $key = str_replace('contact.', '', $key);

            $header[] = ctrans("texts.{$key}");
        }

        return $header;
    }
}
