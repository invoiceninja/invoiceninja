<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use Illuminate\Support\Carbon;

/**
 * Class MakesReminders.
 */
trait MakesReminders
{

    public function setReminder($settings = null)
    {
        if(!$settings)
            $settings = $this->client->getMergedSettings();

        if(!$this->isPayable())
        {
            $this->next_send_date = null;
            $this->save();
            return; //exit early
        }

        $nsd = null;

        if($settings->enable_reminder1 !== false && 
            $settings->schedule_reminder1 == 'after_invoice_date' &&
            $settings->num_days_reminder1 > 0) 
        {
            $reminder_date = Carbon::parse($this->date)->addDays($settings->num_days_reminder1);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;

        }
        
        if($settings->enable_reminder1 !== false && 
            $settings->schedule_reminder1 == 'before_due_date' &&
            $settings->num_days_reminder1 > 0) 
        {
            $reminder_date = Carbon::parse($this->due_date)->subDays($settings->num_days_reminder1);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;
        }


        if($settings->enable_reminder1 !== false && 
            $settings->schedule_reminder1 == 'after_due_date' &&
            $settings->num_days_reminder1 > 0) 
        {
            $reminder_date = Carbon::parse($this->due_date)->addDays($settings->num_days_reminder1);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;
        }
        
        if($settings->enable_reminder2 !== false && 
            $settings->schedule_reminder2 == 'after_invoice_date' &&
            $settings->num_days_reminder2 > 0) 
        {
            $reminder_date = Carbon::parse($this->date)->addDays($settings->num_days_reminder2);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;

        }
        
        if($settings->enable_reminder2 !== false && 
            $settings->schedule_reminder2 == 'before_due_date' &&
            $settings->num_days_reminder2 > 0) 
        {
            $reminder_date = Carbon::parse($this->due_date)->subDays($settings->num_days_reminder2);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;
        }


        if($settings->enable_reminder2 !== false && 
            $settings->schedule_reminder2 == 'after_due_date' &&
            $settings->num_days_reminder2 > 0) 
        {
            $reminder_date = Carbon::parse($this->due_date)->addDays($settings->num_days_reminder2);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;
        }

        if($settings->enable_reminder3 !== false && 
            $settings->schedule_reminder3 == 'after_invoice_date' &&
            $settings->num_days_reminder3 > 0) 
        {
            $reminder_date = Carbon::parse($this->date)->addDays($settings->num_days_reminder3);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;

        }
        
        if($settings->enable_reminder3 !== false && 
            $settings->schedule_reminder3 == 'before_due_date' &&
            $settings->num_days_reminder3 > 0) 
        {
            $reminder_date = Carbon::parse($this->due_date)->subDays($settings->num_days_reminder3);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;
        }


        if($settings->enable_reminder3 !== false && 
            $settings->schedule_reminder3 == 'after_due_date' &&
            $settings->num_days_reminder3 > 0) 
        {
            $reminder_date = Carbon::parse($this->due_date)->addDays($settings->num_days_reminder3);

            if(!$nsd)
                $nsd = $reminder_date;
            
            if($reminder_date->lt($nsd))
                $nsd = $reminder_date;
        }
                
        $this->next_send_date = $nsd;
        $this->save();

    }

}

