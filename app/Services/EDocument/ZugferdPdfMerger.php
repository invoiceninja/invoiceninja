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

namespace App\Services\EDocument;

use App\Jobs\EDocument\CreateEDocument;
use App\Models\Invoice;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilderAbstract;

class ZugferdPdfMerger
{
    private const DEFAULT_PROFILE = 'XInvoice_3_0';

    private const ZUGFERD_PROFILES = [
        'EN16931',
        'XInvoice_3_0',
        'XInvoice_2_3',
        'XInvoice_2_2',
        'XInvoice_2_1',
        'XInvoice_2_0',
        'XInvoice_1_0',
        'XInvoice-Extended',
        'XInvoice-BasicWL',
        'XInvoice-Basic',
    ];

    public function __construct(private Invoice $invoice, private string $pdf, private ?string $profile = null)
    {
    }

    public static function shouldMerge(Invoice $invoice, object $settings): bool
    {
        return (bool) ($settings->enable_e_invoice ?? false)
            && (bool) ($settings->merge_e_invoice_to_pdf ?? false)
            && self::isZugferdProfile((string) ($settings->e_invoice_type ?? self::profileFor($invoice)));
    }

    public static function isZugferdProfile(?string $profile): bool
    {
        return in_array(self::normalizeProfile($profile), self::ZUGFERD_PROFILES, true);
    }

    public static function attachmentRelationshipType(?string $profile): string
    {
        return match (self::normalizeProfile($profile)) {
            // ZUGFeRD/Factur-X allows Data for booking-aid profiles. All full
            // invoice profiles should be associated as an alternate rendering.
            'XInvoice-BasicWL' => ZugferdDocumentPdfBuilderAbstract::AF_RELATIONSHIP_DATA,
            default => ZugferdDocumentPdfBuilderAbstract::AF_RELATIONSHIP_ALTERNATIVE,
        };
    }

    public static function profileFor(Invoice $invoice): string
    {
        return self::normalizeProfile($invoice->client->getSetting('e_invoice_type') ?? null);
    }

    public static function normalizeProfile(?string $profile): string
    {
        return is_string($profile) && strlen($profile) > 2 ? $profile : self::DEFAULT_PROFILE;
    }

    public function handle(): string
    {
        $profile = self::normalizeProfile($this->profile ?? self::profileFor($this->invoice));

        if (!self::isZugferdProfile($profile)) {
            return $this->pdf;
        }

        return ZugferdDocumentPdfBuilder::fromPdfString($this->createDocument(), $this->pdf)
            ->setAdditionalCreatorTool($this->creatorTool())
            ->setAttachmentRelationshipType(self::attachmentRelationshipType($profile))
            ->generateDocument()
            ->downloadString();
    }

    protected function createDocument(): ZugferdDocumentBuilder
    {
        $document = (new CreateEDocument($this->invoice, true))->handle();

        if (!$document instanceof ZugferdDocumentBuilder) {
            throw new \UnexpectedValueException('Unable to merge e-invoice into PDF: ZUGFeRD document builder was not returned.');
        }

        return $document;
    }

    private function creatorTool(): string
    {
        try {
            return (string) config('ninja.app_name', 'Invoice Ninja');
        } catch (\Throwable) {
            return 'Invoice Ninja';
        }
    }
}
