<?php

namespace App\Traits;

use Carbon\Carbon;

trait FormatEmail
{

    private function parseTemplate(string $template_data, bool $is_markdown = true, $contact): string
    {
        $invoice_variables = $this->makeValues($contact);

        //process variables
        $data = str_replace(array_keys($invoice_variables), array_values($invoice_variables), $template_data);

        //process markdown
        if ($is_markdown) {
            //$data = Parsedown::instance()->line($data);

            $converter = new CommonMarkConverter([
                'html_input' => 'allow',
                'allow_unsafe_links' => true,
            ]);

            $data = $converter->convertToHtml($data);
        }

        return $data;
    }

    private function inReminderWindow($schedule_reminder, $num_days_reminder)
    {
        switch ($schedule_reminder) {
            case 'after_invoice_date':
                return Carbon::parse($this->invoice->date)->addDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            case 'before_due_date':
                return Carbon::parse($this->invoice->due_date)->subDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            case 'after_due_date':
                return Carbon::parse($this->invoice->due_date)->addDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            default:
                # code...
                break;
        }
    }
}
