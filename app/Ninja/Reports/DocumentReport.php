<?php

namespace App\Ninja\Reports;

use App\Models\Invoice;
use App\Models\Expense;
use Barracuda\ArchiveStream\Archive;

class DocumentReport extends AbstractReport
{
    public $columns = [
        'document',
        'client',
        'invoice_or_expense',
        'date',
    ];

    public function run()
    {
        $account = auth()->user()->account;
        $filter = $this->options['document_filter'];
        $exportFormat = $this->options['export_format'];
        $records = false;

        if (! $filter || $filter == ENTITY_INVOICE) {
            $records = Invoice::scope()
                            ->withArchived()
                            ->with(['documents'])
                            ->where('invoice_date', '>=', $this->startDate)
                            ->where('invoice_date', '<=', $this->endDate)
                            ->get();
        }

        if (! $filter || $filter == ENTITY_EXPENSE){
            $expenses = Expense::scope()
                            ->withArchived()
                            ->with(['documents'])
                            ->where('expense_date', '>=', $this->startDate)
                            ->where('expense_date', '<=', $this->endDate)
                            ->get();

            if ($records) {
                $records = $records->merge($expenses);
            } else {
                $records = $expenses;
            }
        }

        if ($this->isExport && $exportFormat == 'zip') {
            $zip = Archive::instance_by_useragent(date('Y-m-d') . '_' . str_replace(' ', '_', trans('texts.documents')));
            foreach ($records as $record) {
                foreach ($record->documents as $document) {
                    $name = sprintf('%s_%s_%s', $document->created_at->format('Y-m-d'), $record->present()->titledName, $document->name);
                    $name = str_replace(' ', '_', $name);
                    $name = str_replace('#', '', $name);
                    $zip->add_file($name, $document->getRaw());
                }
            }
            $zip->finish();
            exit;
        }

        foreach ($records as $record) {
            foreach ($record->documents as $document) {
                $this->data[] = [
                    $this->isExport ? $document->name : link_to($document->getUrl(), $document->name),
                    $record->client ? ($this->isExport ? $record->client->getDisplayName() : $record->client->present()->link) : '',
                    $this->isExport ? $record->present()->titledName : ($filter ? $record->present()->link : link_to($record->present()->url, $record->present()->titledName)),
                    $record->getEntityType() == ENTITY_INVOICE ? $record->invoice_date : $record->expense_date,
                ];
            }
        }
    }
}
