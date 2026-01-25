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

namespace App\Services\EDocument\Standards;

use App\Models\Invoice;
use App\Models\VerifactuLog;
use App\Services\AbstractService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use App\Services\EDocument\Standards\Verifactu\AeatClient;
use App\Services\EDocument\Standards\Verifactu\RegistroAlta;
use App\Services\EDocument\Standards\Verifactu\Models\Invoice as VerifactuInvoice;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use App\Utils\Traits\MakesHash;

class Verifactu extends AbstractService
{
    use MakesHash;

    private AeatClient $aeat_client;

    private string $soapXml;

    //store the current document state
    private VerifactuInvoice $_document;

    public RegistroAlta $registro_alta;

    //store the current huella
    private string $_huella;

    private string $_previous_huella;

    public function __construct(public Invoice $invoice)
    {
        $this->aeat_client = new AeatClient();
    }

    /**
     * Entry point for building document
     *
     * @return self
     */
    public function run(): self
    {

        $v_logs = $this->invoice->company->verifactu_logs;

        $i_logs = $this->invoice->verifactu_logs;

        $registro_alta = (new RegistroAlta($this->invoice))->run();

        if ($this->invoice->amount < 0) {
            $registro_alta = $registro_alta->setRectification();
        }

        $this->registro_alta = $registro_alta;

        $document = $registro_alta->getInvoice();

        //keep this state for logging later on successful send
        $this->_document = $document;

        $this->_previous_huella = '';

        if ($v_logs->count() >= 1) {
            $v_log = $v_logs->first();
            $this->_previous_huella = $v_log->hash;
        }

        $this->_huella = $this->calculateHash($document, $this->_previous_huella); // careful with this! we'll need to reference this later
        $document->setHuella($this->_huella);

        $this->setEnvelope($document->toSoapEnvelope());

        return $this;

    }

    /**
     * setHuella
     * We need this for cancellation documents.
     *
     * @param  string $huella
     * @return self
     */
    public function setHuella(string $huella): self
    {
        $this->_huella = $huella;
        return $this;
    }

    public function getInvoice()
    {
        return $this->_document;
    }

    public function setInvoice(VerifactuInvoice $invoice): self
    {
        $this->_document = $invoice;
        return $this;
    }

    public function getEnvelope(): string
    {
        return $this->soapXml;
    }

    public function setTestMode(): self
    {
        $this->aeat_client->setTestMode();
        return $this;
    }
    /**
     * setPreviousHash
     *
     * **only used for testing**
     * @param  string $previous_hash
     * @return self
     */
    public function setPreviousHash(string $previous_hash): self
    {
        $this->_previous_huella = $previous_hash;
        return $this;
    }

    private function setEnvelope(string $soapXml): self
    {
        $this->soapXml = $soapXml;
        return $this;
    }

    public function writeLog(array $response)
    {
        VerifactuLog::create([
            'invoice_id' => $this->invoice->id,
            'company_id' => $this->invoice->company_id,
            'invoice_number' => $this->invoice->number,
            'date' => $this->invoice->date,
            'hash' => $this->_huella,
            'nif' => $this->_document->getIdFactura()->getIdEmisorFactura(),
            'previous_hash' => $this->_previous_huella,
            'state' => $this->_document->serialize(),
            'response' => $response,
            'status' => $response['guid'],
        ]);
    }
    /**
     * calculateHash
     *
     * @param  mixed $document
     * @param  string $huella
     * @return string
     */
    public function calculateHash($document, string $huella): string
    {

        $idEmisorFactura = $document->getIdFactura()->getIdEmisorFactura();
        $numSerieFactura = $document->getIdFactura()->getNumSerieFactura();
        $fechaExpedicionFactura = $document->getIdFactura()->getFechaExpedicionFactura();
        $tipoFactura = $document->getTipoFactura();
        $cuotaTotal = $document->getCuotaTotal();
        $importeTotal = $document->getImporteTotal();
        $fechaHoraHusoGenRegistro = $document->getFechaHoraHusoGenRegistro();

        $hashInput = "IDEmisorFactura={$idEmisorFactura}&" .
            "NumSerieFactura={$numSerieFactura}&" .
            "FechaExpedicionFactura={$fechaExpedicionFactura}&" .
            "TipoFactura={$tipoFactura}&" .
            "CuotaTotal={$cuotaTotal}&" .
            "ImporteTotal={$importeTotal}&" .
            "Huella={$huella}&" .
            "FechaHoraHusoGenRegistro={$fechaHoraHusoGenRegistro}";

        return strtoupper(hash('sha256', $hashInput));
    }


    public function calculateQrCode(VerifactuLog $log)
    {

        try {
            $csv = $log->status;
            $nif = $log->nif;
            $invoiceNumber = $log->invoice_number;
            $date = $log->date->format('d-m-Y');
            $totalAmount = $log->invoice->amount;

            // Intentar usar el importe que realmente se envió a la AEAT (sin retenciones)
            try {
                $state = @unserialize($log->state);

                if ($state instanceof VerifactuInvoice) {
                    $stateTotal = $state->getImporteTotal();

                    if (is_numeric($stateTotal)) {
                        $totalAmount = (float)$stateTotal;
                    }
                }
            } catch (\Throwable $e) {
                nlog('VERIFACTU WARNING: [qr-state]' . $e->getMessage());
            }

            $total = (string)round($totalAmount, 2);

            $url = sprintf(
                $this->aeat_client->base_qr_url,
                urlencode($csv),
                urlencode($nif),
                urlencode($invoiceNumber),
                urlencode($date),
                urlencode($total)
            );

            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($url)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::Medium) // AEAT: level M or higher
                ->size(300) // AEAT minimum recommended: 30x30 mm ≈ 300px @ 254 DPI
                ->margin(10)
                ->labelText('VERI*FACTU')
                ->labelFont(new OpenSans(14))
                ->build();

            return $result->getString();

        } catch (\Exception $e) {
            nlog("VERIFACTU ERROR: [qr]" . $e->getMessage());
            return '';
        }
    }

    public function send(string $soapXml): array
    {
        nlog("VERIFACTU: [send]" . $soapXml);

        $response =  $this->aeat_client->send($soapXml);

        if ($response['success'] || $response['status'] == 'ParcialmenteCorrecto') {
            $this->writeLog($response);
        }

        return $response;
    }
}
