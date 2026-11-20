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
 * except that spaces are kept, and is at most 150 characters before the
 * extension. Only the variables the pattern uses are resolved, and a name is
 * built once per document and pattern.
 */
class FileNamePattern
{
    private const MAX_LENGTH = 150;

    /** @var \WeakMap<Invoice|Quote|Credit|PurchaseOrder, array<string, ?string>>|null */
    private static ?\WeakMap $names = null;

    public function __construct(private Invoice|Quote|Credit|PurchaseOrder $entity)
    {
    }

    /**
     * The relations make() reads, for eager loading a list of documents.
     *
     * @param class-string $entity_class
     * @param string $prefix the path to the document, eg quote. for quote invitations
     * @return array<int, string>
     */
    public static function relations(string $entity_class, string $prefix = ''): array
    {
        $relations = $entity_class === PurchaseOrder::class
            ? ['vendor.company', 'vendor.language']
            : ['client.company', 'client.group_settings'];

        return array_map(fn ($relation) => $prefix . $relation, $relations);
    }

    /**
     * The file name for the entity, or null when it has no pattern and the
     * caller keeps the default name.
     */
    public function make(string $extension = 'pdf'): ?string
    {
        $number = (string) $this->entity->number;

        if (strlen($number) == 0) {
            return null;
        }

        // The pattern cascades from the client and its group, so the owner is needed to read it.
        $owner = $this->entity instanceof PurchaseOrder ? $this->entity->vendor : $this->entity->client;

        if (! $owner) { //@phpstan-ignore-line
            return null;
        }

        $pattern = (string) $owner->getSetting($this->settingKey());

        if (! str_contains($pattern, '$number')) {
            return null;
        }

        $slug = (bool) $owner->getSetting('filename_slug');
        $key = implode("\0", [$number, $pattern, $slug ? '1' : '0', $this->entity->updated_at, $owner->updated_at]);

        self::$names ??= new \WeakMap();
        $names = self::$names[$this->entity] ?? [];

        if (! array_key_exists($key, $names)) {
            $names[$key] = $this->build($owner, $pattern, $slug);
            self::$names[$this->entity] = $names;
        }

        return $names[$key] === null ? null : $names[$key] . '.' . $extension;
    }

    /** The name without its extension, or null when the number would not survive. */
    private function build($owner, string $pattern, bool $slug): ?string
    {
        $locale = $owner->locale();
        $values = $this->values($owner, $locale, $pattern);

        // A long client name or custom value must not push the number out: the values are shortened, never the number.
        for ($limit = 40; $limit >= 0; $limit--) {
            $map = [];

            foreach ($values as $key => $value) {
                $value = $key == '$number' ? $value : Str::limit($value, $limit, '');

                $map[$key] = $value;
                $map['{' . $key . '}'] = $value;
            }

            $name = $this->clean(strtr($pattern, $map), $locale, $slug);

            if (strlen($name) <= self::MAX_LENGTH) {
                break;
            }
        }

        $name = trim(mb_strcut($name, 0, self::MAX_LENGTH, 'UTF-8'), ' .-_');
        $number = trim($this->clean($values['$number'], $locale, $slug), ' .-_');

        // Two documents must never share a name, so one that lost its number keeps the default name.
        return strlen($name) && str_contains($name, $number) ? $name : null;
    }

    /** The same characters as numberFormatter(), but spaces are kept. */
    private function clean(string $value, string $locale, bool $slug): string
    {
        $value = Str::ascii($value, $locale);
        $value = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $value);
        $value = mb_ereg_replace("([\.]{2,})", '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        if ($slug) {
            $value = preg_replace('/[^a-z0-9.]+/', '-', strtolower($value));
        }

        return $value;
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

    /** The variables the pattern uses, keyed by their HtmlEngine names. */
    private function values($owner, string $locale, string $pattern): array
    {
        $helpers = new Helpers();
        $company = $owner->company;
        // Clients and vendors load their contacts with them, primary first.
        $contact = fn () => $owner->contacts->first();

        $values = [
            '$number' => fn () => (string) $this->entity->number,
            '$entity' => fn () => $this->entityLabel($owner, $locale),
            '$date' => fn () => $this->entity->date ? Carbon::parse($this->entity->date)->format('Y-m-d') : '',
            '$due_date' => fn () => $this->entity->due_date ? Carbon::parse($this->entity->due_date)->format('Y-m-d') : '',
            '$company.name' => fn () => (string) $owner->getSetting('name'),
            '$contact.first_name' => fn () => (string) $contact()?->first_name,
            '$contact.last_name' => fn () => (string) $contact()?->last_name,
        ];

        if ($this->entity instanceof PurchaseOrder) {
            $values['$vendor.name'] = fn () => (string) $owner->present()->name();
            $values['$vendor.number'] = fn () => (string) $owner->number;
            $values['$vendor.id_number'] = fn () => (string) $owner->id_number;

            foreach ([1, 2, 3, 4] as $i) {
                $values['$vendor.custom' . $i] = fn () => (string) $helpers->formatCustomFieldValue($company->custom_fields, 'vendor' . $i, $owner->{'custom_value' . $i}, $company); //@phpstan-ignore-line
            }
        } else {
            $values['$po_number'] = fn () => (string) $this->entity->po_number;
            $values['$client.name'] = fn () => (string) $this->entity->present()->clientName();
            $values['$client.number'] = fn () => (string) $owner->number;
            $values['$client.id_number'] = fn () => (string) $owner->id_number;

            foreach ([1, 2, 3, 4] as $i) {
                $values['$client.custom' . $i] = fn () => (string) $helpers->formatCustomFieldValue($company->custom_fields, 'client' . $i, $owner->{'custom_value' . $i}, $owner);
            }
        }

        $values = array_filter($values, fn ($key) => $key == '$number' || str_contains($pattern, $key), ARRAY_FILTER_USE_KEY);

        return array_map(fn ($value) => $value(), $values);
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
