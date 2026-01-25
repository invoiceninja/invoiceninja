<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Validation;

class XsltDocumentValidator
{
    private array $stylesheets = [
        '/Services/EDocument/Standards/Validation/Peppol/Stylesheets/CEN-EN16931-UBL.xslt',
        '/Services/EDocument/Standards/Validation/Peppol/Stylesheets/PEPPOL-EN16931-UBL.xslt',
    ];

    private string $ubl_xsd = 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-Invoice-2.1.xsd';

    private string $ubl_credit_note_xsd = 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-CreditNote-2.1.xsd';

    private string $peppol_stylesheet = 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/generic_stylesheet.xslt';

    public array $errors = [];

    private bool $isCreditNote = false;

    public function __construct(public string $xml_document)
    {
        $this->isCreditNote = $this->detectDocumentType() === 'creditnote';
    }

    /**
     * Detect the document type from XML content
     *
     * @return string 'invoice' or 'creditnote'
     */
    private function detectDocumentType(): string
    {
        // Check for CreditNote root element (with or without namespace prefix)
        if (preg_match('/<CreditNote[^>]*>/i', $this->xml_document) ||
            preg_match('/<[a-z0-9]+:CreditNote[^>]*>/i', $this->xml_document)) {
            return 'creditnote';
        }

        return 'invoice';
    }

    /**
     * Get the appropriate XSD path based on document type
     *
     * @return string
     */
    private function getXsdPath(): string
    {
        return $this->isCreditNote ? $this->ubl_credit_note_xsd : $this->ubl_xsd;
    }

    /**
     * Check if the document is a Credit Note
     *
     * @return bool
     */
    public function isCreditNote(): bool
    {
        return $this->isCreditNote;
    }

    /**
     * Validate the XSLT document
     *
     * @return self
     */
    public function validate(): self
    {
        // nlog($this->xml_document);
        $this->validateXsd()
             ->validateSchema();

        return $this;
    }

    private function validateSchema(): self
    {

        try {
            $processor = new \Saxon\SaxonProcessor();

            $xslt = $processor->newXslt30Processor();

            foreach ($this->stylesheets as $stylesheet) {
                $xdmNode = $processor->parseXmlFromString($this->xml_document);

                /** @var \Saxon\XsltExecutable $xsltExecutable */
                $xsltExecutable = $xslt->compileFromFile(app_path($stylesheet)); //@phpstan-ignore-line
                $result = $xsltExecutable->transformToValue($xdmNode); //@phpstan-ignore-line

                if ($result->size() == 0) {
                    continue;
                }

                for ($x = 0; $x < $result->size(); $x++) {
                    $a = $result->itemAt($x);

                    if (strlen($a->getStringValue() ?? '') > 1) {
                        $this->errors['stylesheet'][] = $a->getStringValue();
                    }
                }

            }

        } catch (\Throwable $th) {

            $this->errors['general'][] = $th->getMessage();
        }

        return $this;

    }

    private function validateXsd(): self
    {
        libxml_use_internal_errors(true);

        $xml = new \DOMDocument();
        $xml->loadXML($this->xml_document);

        if (!$xml->schemaValidate(app_path($this->getXsdPath()))) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            foreach ($errors as $error) {
                $this->errors['xsd'][] = sprintf(
                    'Line %d: %s',
                    $error->line,
                    trim($error->message)
                );
            }
        }

        return $this;
    }

    public function setXsd(string $xsd): self
    {
        $this->ubl_xsd = $xsd;

        return $this;
    }

    public function getXsd(): string
    {
        return $this->ubl_xsd;
    }

    public function setStyleSheets(array $stylesheets): self
    {
        $this->stylesheets = $stylesheets;

        return $this;
    }

    public function getStyleSheets(): array
    {
        return $this->stylesheets;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getHtml(): mixed
    {
        //@todo need to harvest the document type and apply the correct stylesheet
        try {
            // Create Saxon processor
            $processor = new \Saxon\SaxonProcessor();
            $xslt = $processor->newXslt30Processor();

            $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->xml_document);

            // Load XML document
            $xml_doc = $processor->parseXmlFromString($xml);

            // Compile and apply stylesheet
            /** @var \Saxon\XsltExecutable $stylesheet */
            $stylesheet = $xslt->compileFromFile(app_path($this->peppol_stylesheet)); //@phpstan-ignore-line

            // Transform to HTML
            $result = $stylesheet->transformToString($xml_doc); //@phpstan-ignore-line

            return $result;

        } catch (\Throwable $th) {
            nlog("failed to convert xml to html ".$th->getMessage());
            return ['errors' => $th->getMessage()];
            // Handle any errors
            // throw new \Exception("XSLT transformation failed: " . $e->getMessage());
        }

    }

}
