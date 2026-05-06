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

namespace App\Services\Pdf;

/**
 * Resolves the seven operational + three visual settings consumed by the PDF
 * stack when rendering a JSON-designer document.
 *
 * Precedence: design.documentSettings (per-template override) → company/client
 * merged settings (existing behavior). A field is treated as overridden only
 * when it is *present* in documentSettings — `false` is a valid override.
 *
 * Returns a cloned settings object so the caller may safely swap it onto
 * PdfConfiguration without mutating shared state (Octane-safe).
 */
class DocumentSettingsResolver
{
    /**
     * @var array<string, string> documentSettings (camel) → settings (snake)
     */
    private const FIELD_MAP = [
        'pageSize'             => 'page_size',
        'pageLayout'           => 'page_layout',
        'globalFontSize'       => 'font_size',
        'primaryFont'          => 'primary_font',
        'secondaryFont'        => 'secondary_font',
        'showPaidStamp'        => 'show_paid_stamp',
        'showShippingAddress'  => 'show_shipping_address',
        'embedDocuments'       => 'embed_documents',
        'hideEmptyColumns'     => 'hide_empty_columns_on_pdf',
        'pageNumbering'        => 'page_numbering',
    ];

    /**
     * @param array $design Decoded design payload (expects optional 'documentSettings' key)
     * @param object $settings Merged company/client settings stdClass
     */
    public function __construct(private array $design, private object $settings) {}

    /**
     * Build a cloned settings object with documentSettings overrides applied.
     */
    public function resolve(): object
    {
        $clone = $this->cloneSettings();
        $documentSettings = $this->extractDocumentSettings();

        foreach (self::FIELD_MAP as $designKey => $settingKey) {
            if (!array_key_exists($designKey, $documentSettings)) {
                continue;
            }

            $clone->{$settingKey} = $documentSettings[$designKey];
        }

        return $clone;
    }

    /**
     * Whether the design carries a documentSettings block at all.
     */
    public function hasOverrides(): bool
    {
        return $this->extractDocumentSettings() !== [];
    }

    /**
     * Pull documentSettings from the design array. Tolerates the legacy
     * `pageSettings` key only when no `documentSettings` is present, and only
     * for keys that overlap (none of the operational fields do, so this is
     * effectively a no-op for legacy designs — they fall back to company
     * settings, which is the desired behavior).
     *
     * @return array<string, mixed>
     */
    private function extractDocumentSettings(): array
    {
        $documentSettings = $this->design['documentSettings'] ?? null;

        if (is_array($documentSettings)) {
            return $documentSettings;
        }

        return [];
    }

    /**
     * Shallow clone the settings stdClass. Sufficient because the resolver
     * only ever writes scalar values (page_size, page_layout, font_size,
     * primary/secondary_font, four bool flags) — those properties land on
     * the clone alone. The single nested object (`pdf_variables`) remains
     * reference-shared with the original, which is safe in this code path:
     * pdf_variables is consumed once at PdfConfiguration::setPdfVariables()
     * BEFORE the swap, and nothing mutates it after.
     *
     * Avoiding json_decode(json_encode()) saves a full serialize/parse on
     * every render; avoiding unserialize(serialize()) keeps us off PHP's
     * object-reconstruction surface entirely.
     */
    private function cloneSettings(): object
    {
        return clone $this->settings;
    }
}
