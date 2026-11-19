<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds a document's file name from the company's file name pattern
 * (invoice_filename_pattern, quote_filename_pattern, credit_filename_pattern,
 * purchase_order_filename_pattern).
 *
 * The variables are the HtmlEngine / VendorHtmlEngine names used in email
 * templates and designs ($number, $client.name, ...), replaced with strtr like
 * TemplateEngine, and may also be written in braces ({$client.number}). Dates
 * are written Y-m-d, since a date format with slashes would be stripped. A
 * pattern must contain $number so that two documents never share a name.
 *
 * The result is cleaned with the same rules as BaseModel::numberFormatter(),
 * except that spaces are kept.
 */
class FileNamePattern
{
    public function __construct(private Invoice|Quote|Credit|PurchaseOrder $entity)
    {
    }

    /**
     * The file name for the entity, or null when it has no pattern and the
     * caller keeps the default name.
     */
    public function make(string $extension = 'pdf'): ?string
    {
        $owner = $this->entity instanceof PurchaseOrder ? $this->entity->vendor : $this->entity->client;

        if (! $owner || strlen($this->entity->number ?? '') == 0) { //@phpstan-ignore-line
            return null;
        }

        $pattern = (string) $owner->getSetting($this->settingKey());

        if (! str_contains($pattern, '$number')) {
            return null;
        }

        $locale = $owner->locale();
        $map = [];

        foreach ($this->values($owner, $locale) as $key => $value) {
            // A long client name or custom value must not push the number out.
            $value = $key == '$number' ? $value : Str::limit($value, 40, '');

            $map[$key] = $value;
            $map['{' . $key . '}'] = $value;
        }

        $name = Str::ascii(strtr($pattern, $map), $locale);

        // The same characters as numberFormatter(), but spaces are kept.
        $name = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $name);
        $name = mb_ereg_replace("([\.]{2,})", '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        if ($owner->getSetting('filename_slug')) {
            $name = preg_replace('/[^a-z0-9.]+/', '-', strtolower($name));
        }

        $name = trim(mb_strcut($name, 0, 150, 'UTF-8'), ' .-_');

        return strlen($name) ? $name . '.' . $extension : null;
    }

    private function settingKey(): string
    {
        return match (true) {
            $this->entity instanceof Invoice => 'invoice_filename_pattern',
            $this->entity instanceof Quote => 'quote_filename_pattern',
            $this->entity instanceof Credit => 'credit_filename_pattern',
            default => 'purchase_order_filename_pattern',
        };
    }

    /** The variables, keyed by their HtmlEngine names. */
    private function values($owner, string $locale): array
    {
        $helpers = new Helpers();
        $company = $this->entity->company;
        $contact = $owner->contacts()->first();

        $values = [
            '$number' => (string) $this->entity->number,
            '$entity' => $this->entityLabel($owner, $locale),
            '$date' => $this->entity->date ? Carbon::parse($this->entity->date)->format('Y-m-d') : '',
            '$due_date' => $this->entity->due_date ? Carbon::parse($this->entity->due_date)->format('Y-m-d') : '',
            '$company.name' => (string) $owner->getSetting('name'),
            '$contact.first_name' => (string) $contact?->first_name,
            '$contact.last_name' => (string) $contact?->last_name,
        ];

        if ($this->entity instanceof PurchaseOrder) {
            $values['$vendor.name'] = (string) $owner->present()->name();
            $values['$vendor.number'] = (string) $owner->number;
            $values['$vendor.id_number'] = (string) $owner->id_number;

            foreach ([1, 2, 3, 4] as $i) {
                $values['$vendor.custom' . $i] = (string) $helpers->formatCustomFieldValue($company->custom_fields, 'vendor' . $i, $owner->{'custom_value' . $i}, $company); //@phpstan-ignore-line
            }

            return $values;
        }

        $values['$po_number'] = (string) $this->entity->po_number;
        $values['$client.name'] = (string) $this->entity->present()->clientName();
        $values['$client.number'] = (string) $owner->number;
        $values['$client.id_number'] = (string) $owner->id_number;

        foreach ([1, 2, 3, 4] as $i) {
            $values['$client.custom' . $i] = (string) $helpers->formatCustomFieldValue($company->custom_fields, 'client' . $i, $owner->{'custom_value' . $i}, $owner);
        }

        return $values;
    }

    /** The document type, with the company's custom translation if it has one. */
    private function entityLabel($owner, string $locale): string
    {
        $key = match (true) {
            $this->entity instanceof Invoice => 'invoice',
            $this->entity instanceof Quote => 'quote',
            $this->entity instanceof Credit => 'credit',
            default => 'purchase_order',
        };

        $translations = (array) ($owner->getMergedSettings()->translations ?? []);

        return (string) ($translations[$key] ?? html_entity_decode(trans('texts.' . $key, [], $locale)));
    }
}
