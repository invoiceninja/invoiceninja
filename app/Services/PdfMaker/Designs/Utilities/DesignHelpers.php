<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\PdfMaker\Designs\Utilities;

use App\Models\Task;
use App\Utils\Traits\MakesHash;
use DOMDocument;
use DOMXPath;
use Exception;

trait DesignHelpers
{
    use MakesHash;

    public $document;

    public $xpath;

    public function setup(): self
    {
        if (isset($this->context['client'])) {
            $this->client = $this->context['client'];
        }

        if (isset($this->context['entity'])) {
            $this->entity = $this->context['entity'];
        }

        $this->document();

        return $this;
    }

    /**
     * Initialize local dom document instance. Used for getting raw HTML out of template.
     *
     * @return $this
     */
    public function document(): self
    {
        $document = new DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML($this->html());

        $this->document = $document;
        $this->xpath = new DOMXPath($document);

        return $this;
    }

    /**
     * Get specific section HTML.
     *
     * @param string $section
     * @param bool $id
     * @return null|string
     */
    public function getSectionHTML(string $section, $id = true): ?string
    {
        if ($id) {
            $element = $this->document->getElementById($section);
        } else {
            $elements = $this->document->getElementsByTagName($section);
            $element = $elements[0];
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        if ($element) {
            $document->appendChild(
                $document->importNode($element, true)
            );

            $html = $document->saveHTML();

            return str_replace('%24', '$', $html);
        }

        return '';
    }

    /**
     * This method will help us decide either we show
     * one "tax rate" column in the table or 3 custom tax rates.
     *
     * Logic below will help us calculate that & inject the result in the
     * global state of the $context (design state).
     * 
     * @param string $type "product" or "task"
     * @return void
     */
    public function processTaxColumns(string $type): void
    {
        if ($type == 'product') {
            $type_id = 1;
        }

        if ($type == 'task') {
            $type_id = 2;
        }

        // At the moment we pass "task" or "product" as type.
        // However, "pdf_variables" contains "$task.tax" or "$product.tax" <-- Notice the dollar sign.
        // This sprintf() will help us convert "task" or "product" into "$task" or "$product" without
        // evaluating the variable.

        if (in_array(sprintf('%s%s.tax', '$', $type), (array) $this->context['pdf_variables']["{$type}_columns"])) {
            $line_items = collect($this->entity->line_items)->filter(function ($item) use ($type_id) {
                return $item->type_id = $type_id;
            });

            $tax1 = $line_items->where('tax_name1', '<>', '')->where('type_id', $type_id)->count();
            $tax2 = $line_items->where('tax_name2', '<>', '')->where('type_id', $type_id)->count();
            $tax3 = $line_items->where('tax_name3', '<>', '')->where('type_id', $type_id)->count();

            $taxes = [];

            if ($tax1 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate1', '$', $type));
            }

            if ($tax2 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate2', '$', $type));
            }

            if ($tax3 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate3', '$', $type));
            }

            $key = array_search(sprintf('%s%s.tax', '$', $type), $this->context['pdf_variables']["{$type}_columns"], true);

            if ($key) {
                array_splice($this->context['pdf_variables']["{$type}_columns"], $key, 1, $taxes);
            }
        }
    }

    /**
     * Calculates the remaining colspans.
     *
     * @param int $taken
     * @return int
     */
    public function calculateColspan(int $taken): int
    {
        $total = (int) count($this->context['pdf_variables']['product_columns']);

        return (int) $total - $taken;
    }

    /**
     * Return "true" or "false" based on null or empty check.
     * We need to return false as string because of HTML parsing.
     *
     * @param mixed $property
     * @return string
     */
    public function toggleHiddenProperty($property): string
    {
        if (is_null($property)) {
            return 'false';
        }

        if (empty($property)) {
            return 'false';
        }

        return 'true';
    }

    public function sharedFooterElements()
    {
        // return ['element' => 'div', 'properties' => ['style' => 'display: flex; justify-content: space-between; margin-top: 1.5rem; page-break-inside: avoid;'], 'elements' => [
        //     ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 5rem;', 'hidden' => $this->entity->user->account->isPaid() ? 'true' : 'false']],
        // ]];

        return ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 3rem; position: fixed; bottom: 0; left: 0; padding: 5px; margin: 5px;', 'hidden' => $this->entity->user->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']];
    }

    public function entityVariableCheck(string $variable): bool
    {
        // Extract $invoice.date => date
        // so we can append date as $entity->date and not $entity->$invoice.date;

        try {
            $_variable = explode('.', $variable)[1];
        } catch (Exception $e) {
            throw new Exception('Company settings seems to be broken. Missing $entity.variable type.');
        }

        if (is_null($this->entity->{$_variable})) {
            return true;
        }

        if (empty($this->entity->{$_variable})) {
            return true;
        }

        return false;
    }

    public function composeFromPartials(array $partials)
    {
        $html = '';

        $html .= $partials['includes'];
        $html .= $partials['header'];
        $html .= $partials['body'];
        $html .= $partials['footer'];

        return $html;
    }

    public function getTaskTimeLogs(array $row)
    {
        if (!array_key_exists('task_id', $row)) {
            return [];
        }

        $task = Task::find($this->decodePrimaryKey($row['task_id']));

        if (!$task) {
            return [];
        }

        foreach (json_decode($task['time_log']) as $log) {
            info($log);
            $logs[] = sprintf('%s - %s', \Carbon\Carbon::createFromTimestamp($log[0])->toDateTimeString(), \Carbon\Carbon::createFromTimestamp($log[1])->toDateTimeString());
        }

        return $logs;
    }
}
