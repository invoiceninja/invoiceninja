<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use Illuminate\Support\Carbon;

class BaseExport
{

    private function addDateRange($query)
    {

        $date_range = $this->input['date_range'];

        if(array_key_exists('date_key', $this->input))
            $this->date_key = $this->input['date_key'];
        
        try{

            $custom_start_date = Carbon::parse($this->input['start_date']);
            $custom_end_date = Carbon::parse($this->input['end_date']);    

        }
        catch(\Exception $e){

            $custom_start_date = now()->startOfYear();
            $custom_end_date = now();

        }
        
        switch ($date_range) {

            case 'all':
                return $query;
            case 'last7':
                return $query->whereBetween($this->date_key, [now()->subDays(7), now()]);
            case 'last30':
                return $query->whereBetween($this->date_key, [now()->subDays(30), now()]);
            case 'this_month':
                return $query->whereBetween($this->date_key, [now()->startOfMonth(), now()]);
            case 'last_month':
                return $query->whereBetween($this->date_key, [now()->startOfMonth()->subMonth(), now()->startOfMonth()->subMonth()->endOfMonth()]);
            case 'this_quarter':
                return $query->whereBetween($this->date_key, [(new \Carbon\Carbon('-3 months'))->firstOfQuarter(), (new \Carbon\Carbon('-3 months'))->lastOfQuarter()]);
            case 'last_quarter':
                return $query->whereBetween($this->date_key, [(new \Carbon\Carbon('-6 months'))->firstOfQuarter(), (new \Carbon\Carbon('-6 months'))->lastOfQuarter()]);
            case 'this_year':
                return $query->whereBetween($this->date_key, [now()->startOfYear(), now()]);
            case 'custom':
                return $query->whereBetween($this->date_key, [$custom_start_date, $custom_end_date]);
            default:
                return $query->whereBetween($this->date_key, [now()->startOfYear(), now()]);

        }

    }

}