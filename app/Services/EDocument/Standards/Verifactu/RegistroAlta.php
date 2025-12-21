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

namespace App\Services\EDocument\Standards\Verifactu;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\VerifactuLog;
use App\Helpers\Invoice\Taxer;
use App\Utils\Traits\MakesHash;
use App\DataMapper\Tax\BaseRule;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use App\Utils\Traits\NumberFormatter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Services\EDocument\Standards\Verifactu\Models\IDOtro;
use App\Services\EDocument\Standards\Verifactu\Models\Desglose;
use App\Services\EDocument\Standards\Verifactu\Models\IDFactura;
use App\Services\EDocument\Standards\Verifactu\Models\Encadenamiento;
use App\Services\EDocument\Standards\Verifactu\Models\DetalleDesglose;
use App\Services\EDocument\Standards\Verifactu\Models\RegistroAnterior;
use App\Services\EDocument\Standards\Verifactu\Models\SistemaInformatico;
use App\Services\EDocument\Standards\Verifactu\Models\PersonaFisicaJuridica;
use App\Services\EDocument\Standards\Verifactu\Models\Invoice as VerifactuInvoice;
use App\Utils\BcMath;

class RegistroAlta
{
    use Taxer;
    use NumberFormatter;
    use MakesHash;

    private Company $company;

    public InvoiceSum | InvoiceSumInclusive $calc;

    private VerifactuInvoice $v_invoice;

    private ?VerifactuLog $v_log;

    private array $errors = [];

    private string $current_timestamp;

    private array $calculated_invoice_values = [];
    private array $impuesto_codes = [
        '01' => 'IVA (Impuesto sobre el Valor Añadido)', // Value Added Tax - Standard Spanish VAT
        '02' => 'IPSI (Impuesto sobre la Producción, los Servicios y la Importación)', // Production, Services and Import Tax - Ceuta and Melilla
        '03' => 'IGIC (Impuesto General Indirecto Canario)', // Canary Islands General Indirect Tax
        '05' => 'Otros (Others)', // Other taxes
        '06' => 'IAE', //local taxes - rarely used
        '07' => 'Non-Vat / Exempt operations'
    ];

    private array $clave_regimen_codes = [
        '01' => 'Régimen General', // General Regime - Standard VAT regime for most businesses
        '02' => 'Régimen Simplificado', // Simplified Regime - For small businesses with simplified accounting
        '03' => 'Régimen Especial de Agrupaciones de Módulos', // Special Module Grouping Regime - For agricultural activities
        '04' => 'Régimen Especial del Recargo de Equivalencia', // Special Equivalence Surcharge Regime - For retailers
        '05' => 'Régimen Especial de las Agencias de Viajes', // Special Travel Agencies Regime
        '06' => 'Régimen Especial de los Bienes Usados', // Special Used Goods Regime
        '07' => 'Régimen Especial de los Objetos de Arte', // Special Art Objects Regime
        '08' => 'Régimen Especial de las Antigüedades', // Special Antiques Regime
        '09' => 'Régimen Especial de los Objetos de Colección', // Special Collectibles Regime
        '10' => 'Régimen Especial de los Bienes de Inversión', // Special Investment Goods Regime
        '11' => 'Régimen Especial de los Servicios', // Special Services Regime
        '12' => 'Régimen Especial de los Bienes de Inversión y Servicios', // Special Investment Goods and Services Regime
        '13' => 'Régimen Especial de los Bienes de Inversión y Servicios (Inversión del Sujeto Pasivo)', // Special Investment Goods and Services Regime (Reverse Charge)
        '14' => 'Régimen Especial de los Bienes de Inversión y Servicios (Inversión del Sujeto Pasivo - Bienes de Inversión)', // Special Investment Goods and Services Regime (Reverse Charge - Investment Goods)
        '15' => 'Régimen Especial de los Bienes de Inversión y Servicios (Inversión del Sujeto Pasivo - Servicios)', // Special Investment Goods and Services Regime (Reverse Charge - Services)
        '16' => 'Régimen Especial de los Bienes de Inversión y Servicios (Inversión del Sujeto Pasivo - Bienes de Inversión y Servicios)', // Special Investment Goods and Services Regime (Reverse Charge - Investment Goods and Services)
        '17' => 'Régimen Especial de los Bienes de Inversión y Servicios (Inversión del Sujeto Pasivo - Bienes de Inversión y Servicios - Inversión del Sujeto Pasivo)', // Special Investment Goods and Services Regime (Reverse Charge - Investment Goods and Services - Reverse Charge)
        '18' => 'Régimen Especial de los Bienes de Inversión y Servicios (Inversión del Sujeto Pasivo - Bienes de Inversión y Servicios - Inversión del Sujeto Pasivo - Bienes de Inversión)', // Special Investment Goods and Services Regime (Reverse Charge - Investment Goods and Services - Reverse Charge - Investment Goods)
        '19' => 'Régimen Especial de los Bienes de Inversión y Servicios (Inversión del Sujeto Pasivo - Bienes de Inversión y Servicios - Inversión del Sujeto Pasivo - Servicios)', // Special Investment Goods and Services Regime (Reverse Charge - Investment Goods and Services - Reverse Charge - Services)
        '20' => 'Régimen Especial de los Bienes de Inversión y Servicios (Inversión del Sujeto Pasivo - Bienes de Inversión y Servicios - Inversión del Sujeto Pasivo - Bienes de Inversión y Servicios)' // Special Investment Goods and Services Regime (Reverse Charge - Investment Goods and Services - Reverse Charge - Investment Goods and Services)
    ];

    private array $calificacion_operacion_codes = [
        'S1' => 'OPERACIÓN SUJETA Y NO EXENTA - SIN INVERSIÓN DEL SUJETO PASIVO', // Subject and Non-Exempt Operation - Without Reverse Charge
        'S2' => 'OPERACIÓN SUJETA Y NO EXENTA - CON INVERSIÓN DEL SUJETO PASIVO', // Subject and Non-Exempt Operation - With Reverse Charge
        'N1' => 'OPERACIÓN NO SUJETA ARTÍCULO 7, 14, OTROS', // Non-Subject Operation Article 7, 14, Others
        'N2' => 'OPERACIÓN NO SUJETA POR REGLAS DE LOCALIZACIÓN' // Non-Subject Operation by Location Rules
    ];

    public function __construct(public Invoice $invoice)
    {
        $this->company = $invoice->company;
        // $this->calc = $this->invoice->calc();
        $this->v_invoice = new VerifactuInvoice();
    }

    private function setInvoiceValues(Invoice $invoice): self
    {
        $line_items = $invoice->line_items;

        foreach ($line_items as $key => $value) {

            if (stripos($value->tax_name1, 'irpf') !== false) {
                $line_items[$key]->tax_name1 = '';
                $line_items[$key]->tax_rate1 = 0;
            } elseif (stripos($value->tax_name2, 'irpf') !== false) {
                $line_items[$key]->tax_name2 = '';
                $line_items[$key]->tax_rate2 = 0;
            } elseif (stripos($value->tax_name3, 'irpf') !== false) {
                $line_items[$key]->tax_name3 = '';
                $line_items[$key]->tax_rate3 = 0;
            }
        }

        $invoice->line_items = $line_items;

        $this->calc = $invoice->calc();

        return $this;
    }
    /**
     * Entry point for building document
     *
     * @return self
     */
    public function run(): self
    {

        // Get the previous invoice log
        $this->v_log = $this->company->verifactu_logs()->first();

        $this->setInvoiceValues(clone $this->invoice);

        $this->current_timestamp = now()->setTimezone('Europe/Madrid')->format('Y-m-d\TH:i:sP');

        $date = \Carbon\Carbon::parse($this->invoice->date);

        // Ensure it’s not later than "now" in Spain
        $now = \Carbon\Carbon::now('Europe/Madrid');

        if ($date->greaterThan($now)) {
            $date = $now;
            $this->invoice->date = $date->format('Y-m-d');
        }


        $formattedDate = $date->format('d-m-Y');

        $this->v_invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new IDFactura())
                ->setIdEmisorFactura($this->company->settings->vat_number)
                ->setNumSerieFactura($this->invoice->number)
                ->setFechaExpedicionFactura($formattedDate))
            ->setNombreRazonEmisor($this->company->present()->name()) //company name
            ->setTipoFactura('F1') //invoice type
            ->setDescripcionOperacion('Alta')// It IS! manadatory - max chars 500
            ->setCuotaTotal($this->calc->getTotalTaxes()) //total taxes
            ->setImporteTotal($this->calc->getTotal()) //total invoice amount
            ->setFechaHoraHusoGenRegistro($this->current_timestamp) //creation/submission timestamp
            ->setTipoHuella('01') //sha256
            ->setHuella('PLACEHOLDER_HUELLA');

        /** The business entity that is issuing the invoice */
        $emisor = new PersonaFisicaJuridica();
        $emisor->setNif(substr($this->company->settings->vat_number, 0, 9))
                ->setNombreRazon($this->invoice->company->present()->name());

        /** The business entity (Client) that is receiving the invoice */
        $destinatarios = [];
        $destinatario = new PersonaFisicaJuridica();

        //Spanish NIF/VAT
        if ($this->invoice->client->country_id == 724 && strlen($this->invoice->client->vat_number ?? '') > 5) {
            $destinatario
                ->setNif($this->invoice->client->vat_number)
                ->setNombreRazon($this->invoice->client->present()->name());
        } elseif ($this->invoice->client->country_id == 724) { // Spanish Passport

            $destinatario = new IDOtro();
            $destinatario->setNombreRazon($this->invoice->client->present()->name());
            $destinatario->setCodigoPais('ES')
                        ->setIdType('03')
                        ->setId($this->invoice->client->id_number ?? '');

        } else {
            $locationData = $this->invoice->service()->location();

            $destinatario = new IDOtro();
            $destinatario->setNombreRazon($this->invoice->client->present()->name());
            $destinatario->setCodigoPais($locationData['country_code']);

            $br = new \App\DataMapper\Tax\BaseRule();

            if (in_array($locationData['country_code'], $br->eu_country_codes) && strlen($this->invoice->client->vat_number ?? '') > 0) {
                $destinatario->setIdType('03');
                $destinatario->setId($this->invoice->client->vat_number);
            }
        }

        $destinatarios[] = $destinatario;

        $this->v_invoice->setDestinatarios($destinatarios);

        // The tax breakdown
        $desglose = new Desglose();

        //Combine the line taxes with invoice taxes here to get a total tax amount
        $taxes = $this->calc->getTaxMap();

        $desglose_iva = [];

        foreach ($taxes as $tax) {

            $desglose_iva = [
                'Impuesto' => $this->calculateTaxType($tax['name']), //tax type
                'ClaveRegimen' => $this->calculateRegimeClassification($tax['name']), //tax regime classification code
                'CalificacionOperacion' => $this->calculateOperationClassification($tax['name']), //operation classification code
                'BaseImponible' => $tax['base_amount'] ?? $this->calc->getNetSubtotal(), // taxable base amount - fixed: key matches DetalleDesglose::toXml()
                'TipoImpositivo' => $tax['tax_rate'], // Tax Rate
                'Cuota' => $tax['total'] // Tax Amount - fixed: key matches DetalleDesglose::toXml()
            ];

            $detalle_desglose = new DetalleDesglose();
            $detalle_desglose->setDesgloseIVA($desglose_iva);
            $desglose->addDesgloseIVA($detalle_desglose);

        };

        if (count($taxes) == 0) {

            $client_country_code = $this->invoice->client->country->iso_3166_2;

            /** By Default we assume a Spanish transaction */
            $impuesto = 'S2';
            $clave_regimen = '08';
            $calificacion = 'S1';

            $br = new \App\DataMapper\Tax\BaseRule();

            /** EU B2B */
            if (in_array($client_country_code, $br->eu_country_codes) && $this->invoice->client->classification != 'individual') {
                $impuesto = '05';
                $clave_regimen = '05';
                $calificacion = 'N2';
            } /** EU B2C */ elseif (in_array($client_country_code, $br->eu_country_codes) && $this->invoice->client->classification == 'individual') {
                $impuesto = '08';
                $clave_regimen = '05';
                $calificacion = 'N2';
            } else { /** Non-EU */
                $impuesto = '05';
                $clave_regimen = '05';
                $calificacion = 'N2';
            }

            $desglose_iva = [
                'Impuesto' => $impuesto, //tax type
                'ClaveRegimen' => $clave_regimen, //tax regime classification code
                'CalificacionOperacion' => $calificacion, //operation classification code
                'BaseImponible' => $this->calc->getNetSubtotal(), // taxable base amount - fixed: key matches DetalleDesglose::toXml()
            ];

            $detalle_desglose = new DetalleDesglose();
            $detalle_desglose->setDesgloseIVA($desglose_iva);
            $desglose->addDesgloseIVA($detalle_desglose);

        }

        $this->v_invoice->setDesglose($desglose);

        // Encadenamiento
        $encadenamiento = new Encadenamiento();

        // We chain the previous hash to the current invoice to ensure consistency
        if ($this->v_log) {

            $registro_anterior = new RegistroAnterior();
            $registro_anterior->setIDEmisorFactura($this->v_log->nif);
            $registro_anterior->setNumSerieFactura($this->v_log->invoice_number);
            $registro_anterior->setFechaExpedicionFactura($this->v_log->date->format('d-m-Y'));
            $registro_anterior->setHuella($this->v_log->hash);

            $encadenamiento->setRegistroAnterior($registro_anterior);

        } else {

            $encadenamiento->setPrimerRegistro('S');

        }

        $this->v_invoice->setEncadenamiento($encadenamiento);

        //Sending system information - We automatically generate the obligado emision from this later
        $sistema = new SistemaInformatico();
        $sistema
            // ->setNombreRazon('Sistema de Facturación')
            ->setNombreRazon(config('services.verifactu.sender_name')) //must match the cert name
            ->setNif(config('services.verifactu.sender_nif'))
            ->setNombreSistemaInformatico('InvoiceNinja')
            ->setIdSistemaInformatico('77')
            ->setVersion('1.0.03')
            ->setNumeroInstalacion('383')
            ->setTipoUsoPosibleSoloVerifactu('N')
            ->setTipoUsoPosibleMultiOT('S')
            ->setIndicadorMultiplesOT('S');

        $this->v_invoice->setSistemaInformatico($sistema);

        return $this;
    }

    public function setRectification(): self
    {

        $document_type = 'R2';

        //need to harvest the parent invoice!!
        $_i = Invoice::withTrashed()->find($this->decodePrimaryKey($this->invoice->backup->parent_invoice_id));

        if (!$_i) {
            throw new \Exception('Parent invoice not found');
        }

        if (BcMath::lessThan(abs($this->invoice->amount), $_i->amount)) {
            $document_type = 'R1';
        }

        $this->v_invoice->setTipoFactura($document_type);
        $this->v_invoice->setTipoRectificativa('I'); // S for substitutive rectification

        if (strlen($this->invoice->backup->notes ?? '') > 0) {
            $this->v_invoice->setDescripcionOperacion($this->invoice->backup->notes);
        }
        // Set up rectified invoice information
        $facturasRectificadas = [
            [
                'IDEmisorFactura' => $this->company->settings->vat_number,
                'NumSerieFactura' => $_i->number,
                'FechaExpedicionFactura' => \Carbon\Carbon::parse($_i->date)->format('d-m-Y')
            ]
        ];

        $this->v_invoice->setFacturasRectificadas($facturasRectificadas);

        $this->invoice->backup->document_type = $document_type;
        $this->invoice->saveQuietly();

        return $this;
    }

    public function getInvoice(): VerifactuInvoice
    {
        return $this->v_invoice;
    }

    private function calculateRegimeClassification(string $tax_name): string
    {
        $client_country_code = $this->invoice->client->country->iso_3166_2;

        if ($client_country_code == 'ES') {

            if (stripos($tax_name, 'iva') !== false) {
                return '01';
            }

            if (stripos($tax_name, 'igic') !== false) {
                return '03';
            }

            if (stripos($tax_name, 'ipsi') !== false) {
                return '02';
            }

            if (stripos($tax_name, 'otros') !== false) {
                return '05';
            }

            return '01';
        }

        $br = new \App\DataMapper\Tax\BaseRule();
        if (in_array($client_country_code, $br->eu_country_codes) && $this->invoice->client->classification != 'individual') {
            return '08';
        } elseif (in_array($client_country_code, $br->eu_country_codes) && $this->invoice->client->classification == 'individual') {
            return '05';
        }

        return '07';

    }

    private function calculateTaxType(string $tax_name): string
    {
        $client_country_code = $this->invoice->client->country->iso_3166_2;

        if ($client_country_code == 'ES') {

            if (stripos($tax_name, 'iva') !== false) {
                return '01';
            }

            if (stripos($tax_name, 'igic') !== false) {
                return '03';
            }

            if (stripos($tax_name, 'ipsi') !== false) {
                return '02';
            }

            if (stripos($tax_name, 'otros') !== false) {
                return '05';
            }

            return '01';
        }

        $br = new \App\DataMapper\Tax\BaseRule();
        if (in_array($client_country_code, $br->eu_country_codes) && $this->invoice->client->classification != 'individual') {
            return '08';
        } elseif (in_array($client_country_code, $br->eu_country_codes) && $this->invoice->client->classification == 'individual') {
            return '05';
        }

        return '07';
    }

    private function calculateOperationClassification(string $tax_name): string
    {
        if ($this->invoice->client->country_id == 724 || stripos($tax_name, 'iva') !== false) {
            return 'S1';
        }

        return 'N2';
    }

}
