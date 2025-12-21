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

namespace Tests\Feature\EInvoice\Verifactu;

use Tests\TestCase;
use App\Services\EDocument\Standards\Verifactu\Models\Cupon;
use App\Services\EDocument\Standards\Verifactu\Models\Invoice;
use App\Services\EDocument\Standards\Verifactu\Models\Desglose;
use App\Services\EDocument\Standards\Verifactu\Models\Encadenamiento;
use App\Services\EDocument\Standards\Verifactu\Models\DetalleDesglose;
use App\Services\EDocument\Standards\Verifactu\Models\SistemaInformatico;
use App\Services\EDocument\Standards\Validation\VerifactuDocumentValidator;
use App\Services\EDocument\Standards\Verifactu\Models\PrimerRegistroCadena;
use App\Services\EDocument\Standards\Verifactu\Models\PersonaFisicaJuridica;
use App\Services\EDocument\Standards\Verifactu\Models\IDOtro;

class VerifactuModelTest extends TestCase
{
    public function test_and_create_new_invoice_for_non_spanish_client(): void
    {

        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-001')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setRefExterna('REF-123')
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Venta de productos varios')
            ->setCuotaTotal(210.00)
            ->setImporteTotal(1000.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add emitter
        $emisor = new PersonaFisicaJuridica();
        $emisor
            ->setNif('B12345678')
            ->setRazonSocial('Empresa Ejemplo SL');
        $invoice->setTercero($emisor);

        $destinatarios = [];
        $destinatario1 = new IDOtro();
        $destinatario1->setNombreRazon('Cliente 1 SL');
        $destinatarios[] = $destinatario1;

        $invoice->setDestinatarios($destinatarios);

        // Add breakdown
        $desglose = new Desglose();
        $desglose->setDesgloseFactura([
            'Impuesto' => '01',
            'ClaveRegimen' => '01',
            'CalificacionOperacion' => 'S1',
            'BaseImponibleOimporteNoSujeto' => 1000.00,
            'TipoImpositivo' => 21,
            'CuotaRepercutida' => 210.00
        ]);
        $invoice->setDesglose($desglose);

        // Add information system
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001');
        $invoice->setSistemaInformatico($sistema);

        // Add chain
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        // Add coupon
        $cupon = new Cupon();
        $cupon
            ->setIdCupon('CUP-001')
            ->setFechaExpedicionCupon('2023-01-01')
            ->setImporteCupon(50.00)
            ->setDescripcionCupon('Descuento promocional');
        // $invoice->setCupon($cupon);

        $xml = $invoice->toXmlString();

        $xslt = new VerifactuDocumentValidator($xml);
        $xslt->validate();
        $errors = $xslt->getVerifactuErrors();

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(0, $errors);




        // Test deserialization
        $deserialized = Invoice::fromXml($xml);
        nlog($deserialized->toXmlString());
        $this->assertEquals($invoice->getIdVersion(), $deserialized->getIdVersion());
        $this->assertEquals($invoice->getIdFactura(), $deserialized->getIdFactura());
        $this->assertEquals($invoice->getNombreRazonEmisor(), $deserialized->getNombreRazonEmisor());
        $this->assertEquals($invoice->getTipoFactura(), $deserialized->getTipoFactura());
        $this->assertEquals($invoice->getDescripcionOperacion(), $deserialized->getDescripcionOperacion());
        $this->assertEquals($invoice->getCuotaTotal(), $deserialized->getCuotaTotal());
        $this->assertEquals($invoice->getImporteTotal(), $deserialized->getImporteTotal());
    }


    public function testCreateAndSerializeCompleteInvoice(): void
    {

        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-001')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setRefExterna('REF-123')
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Venta de productos varios')
            ->setCuotaTotal(210.00)
            ->setImporteTotal(1000.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add emitter
        $emisor = new PersonaFisicaJuridica();
        $emisor
            ->setNif('B12345678')
            ->setRazonSocial('Empresa Ejemplo SL');
        $invoice->setTercero($emisor);

        // Add breakdown
        $desglose = new Desglose();
        $desglose->setDesgloseFactura([
            'Impuesto' => '01',
            'ClaveRegimen' => '01',
            'CalificacionOperacion' => 'S1',
            'BaseImponibleOimporteNoSujeto' => 1000.00,
            'TipoImpositivo' => 21,
            'CuotaRepercutida' => 210.00
        ]);
        $invoice->setDesglose($desglose);

        // Add information system
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001');
        $invoice->setSistemaInformatico($sistema);

        // Add chain
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        // Add coupon
        $cupon = new Cupon();
        $cupon
            ->setIdCupon('CUP-001')
            ->setFechaExpedicionCupon('2023-01-01')
            ->setImporteCupon(50.00)
            ->setDescripcionCupon('Descuento promocional');
        // $invoice->setCupon($cupon);

        $xml = $invoice->toXmlString();

        $xslt = new VerifactuDocumentValidator($xml);
        $xslt->validate();
        $errors = $xslt->getVerifactuErrors();

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(0, $errors);




        // Test deserialization
        $deserialized = Invoice::fromXml($xml);
        $this->assertEquals($invoice->getIdVersion(), $deserialized->getIdVersion());
        $this->assertEquals($invoice->getIdFactura(), $deserialized->getIdFactura());
        $this->assertEquals($invoice->getNombreRazonEmisor(), $deserialized->getNombreRazonEmisor());
        $this->assertEquals($invoice->getTipoFactura(), $deserialized->getTipoFactura());
        $this->assertEquals($invoice->getDescripcionOperacion(), $deserialized->getDescripcionOperacion());
        $this->assertEquals($invoice->getCuotaTotal(), $deserialized->getCuotaTotal());
        $this->assertEquals($invoice->getImporteTotal(), $deserialized->getImporteTotal());
    }

    public function testCreateAndSerializeSimplifiedInvoice(): void
    {
        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-002')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F2')
            ->setFacturaSimplificadaArt7273('S')
            ->setDescripcionOperacion('Venta de productos varios')
            ->setCuotaTotal(21.00)
            ->setImporteTotal(100.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add breakdown
        $desglose = new Desglose();
        $desglose->setDesgloseIVA([
            'Impuesto' => '01',
            'ClaveRegimen' => '02',
            'CalificacionOperacion' => 'S2',
            'BaseImponibleOimporteNoSujeto' => 100.00,
            'TipoImpositivo' => 21,
            'CuotaRepercutida' => 21.00
        ]);
        $invoice->setDesglose($desglose);

        // Add information system
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001')
            ->setTipoUsoPosibleSoloVerifactu('S')
            ->setTipoUsoPosibleMultiOT('S')
            ->setIndicadorMultiplesOT('S');
        $invoice->setSistemaInformatico($sistema);

        // Add encadenamiento
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        $xml = $invoice->toXmlString();


        $xslt = new VerifactuDocumentValidator($xml);
        $xslt->validate();
        $errors = $xslt->getVerifactuErrors();

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(1, $errors);

        // Test deserialization
        $deserialized = Invoice::fromXml($xml);
        $this->assertEquals($invoice->getIdVersion(), $deserialized->getIdVersion());
        $this->assertEquals($invoice->getIdFactura(), $deserialized->getIdFactura());
        $this->assertEquals($invoice->getNombreRazonEmisor(), $deserialized->getNombreRazonEmisor());
        $this->assertEquals($invoice->getTipoFactura(), $deserialized->getTipoFactura());
    }


    public function testCreateAndSerializeInvoiceWithoutRecipient(): void
    {
        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-004')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Venta de productos varios')
            ->setCuotaTotal(21.00)
            ->setImporteTotal(100.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add information system
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001');
        $invoice->setSistemaInformatico($sistema);

        // Add desglose with correct key names
        $desglose = new Desglose();
        $desglose->setDesgloseIVA([
            'Impuesto' => '01',
            'ClaveRegimen' => '02',
            'CalificacionOperacion' => 'S2',
            'BaseImponibleOimporteNoSujeto' => 100.00,
            'TipoImpositivo' => 21.00,
            'CuotaRepercutida' => 21.00
        ]);
        $invoice->setDesglose($desglose);

        // Add encadenamiento
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        $xml = $invoice->toXmlString();

        $xslt = new VerifactuDocumentValidator($xml);
        $xslt->validate();
        $errors = $xslt->getVerifactuErrors();

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(1, $errors);

        // Test deserialization
        $deserialized = Invoice::fromXml($xml);
        $this->assertEquals($invoice->getIdVersion(), $deserialized->getIdVersion());
        $this->assertEquals($invoice->getIdFactura(), $deserialized->getIdFactura());
        $this->assertEquals($invoice->getNombreRazonEmisor(), $deserialized->getNombreRazonEmisor());
        $this->assertEquals($invoice->getTipoFactura(), $deserialized->getTipoFactura());
        $this->assertEquals($invoice->getCuotaTotal(), $deserialized->getCuotaTotal());
        $this->assertEquals($invoice->getImporteTotal(), $deserialized->getImporteTotal());
    }

    public function testInvalidXmlThrowsException(): void
    {
        $this->expectException(\DOMException::class);

        $invalidXml = '<?xml version="1.0" encoding="UTF-8"?><unclosed>';
        Invoice::fromXml($invalidXml);
    }

    public function testMissingRequiredFieldsThrowsException(): void
    {
        $invoice = new Invoice();


        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: IDVersion');

        $invoice->toXmlString();


    }

    public function testCreateAndSerializeInvoiceWithMultipleRecipients(): void
    {
        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-005')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Venta a múltiples destinatarios')
            ->setCuotaTotal(42.00)
            ->setImporteTotal(200.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add multiple recipients
        $destinatarios = [];
        $destinatario1 = new PersonaFisicaJuridica();
        $destinatario1
            ->setNif('B87654321')
            ->setNombreRazon('Cliente 1 SL');
        $destinatarios[] = $destinatario1;

        $destinatario2 = new PersonaFisicaJuridica();
        $destinatario2
            ->setPais('FR')
            ->setTipoIdentificacion('02')
            ->setNif('FR1235678')
            ->setNombreRazon('Client 2 SARL');
        $destinatarios[] = $destinatario2;

        $invoice->setDestinatarios($destinatarios);

        // Add desglose with proper structure and correct key names
        $desglose = new Desglose();
        $desglose->setDesgloseIVA([
            'Impuesto' => '01',
            'ClaveRegimen' => '01',
            'CalificacionOperacion' => 'S1',
            'BaseImponibleOimporteNoSujeto' => 200.00,
            'TipoImpositivo' => 21.00,
            'CuotaRepercutida' => 42.00
        ]);
        $invoice->setDesglose($desglose);

        // Add encadenamiento (required)
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        // Add sistema informatico
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001')
            ->setTipoUsoPosibleSoloVerifactu('S')
            ->setTipoUsoPosibleMultiOT('S')
            ->setIndicadorMultiplesOT('S');
        $invoice->setSistemaInformatico($sistema);

        // Generate XML string
        $xml = $invoice->toXmlString();

        $xslt = new VerifactuDocumentValidator($xml);
        $xslt->validate();
        $errors = $xslt->getVerifactuErrors();

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(1, $errors);

        // Test deserialization
        $deserialized = Invoice::fromXml($xml);
        $this->assertEquals(2, count($deserialized->getDestinatarios()));

        // Verify first recipient (with NIF)
        $this->assertEquals('Cliente 1 SL', $deserialized->getDestinatarios()[0]->getNombreRazon());
        $this->assertEquals('B87654321', $deserialized->getDestinatarios()[0]->getNif());

        // Verify second recipient (with IDOtro)
        $this->assertEquals('Client 2 SARL', $deserialized->getDestinatarios()[1]->getNombreRazon());

    }

    public function testCreateAndSerializeInvoiceWithExemptOperation(): void
    {
        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-006')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Operación exenta de IVA')
            ->setCuotaTotal(0.00)
            ->setImporteTotal(100.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add desglose with exempt operation
        $desglose = new Desglose();
        $desglose->setDesgloseIVA([
            'Impuesto' => '01',
            'ClaveRegimen' => '01',
            'CalificacionOperacion' => 'N1',
            'BaseImponibleOimporteNoSujeto' => 100.00,
            'TipoImpositivo' => 0,
            'CuotaRepercutida' => 0.00
        ]);
        $invoice->setDesglose($desglose);

        // Add encadenamiento (required)
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        // Add sistema informatico
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001')
            ->setTipoUsoPosibleSoloVerifactu('S')
            ->setTipoUsoPosibleMultiOT('S')
            ->setIndicadorMultiplesOT('S');
        $invoice->setSistemaInformatico($sistema);

        // Generate XML string
        $xml = $invoice->toXmlString();

        // Debug output
        // echo "\nGenerated XML:\n";
        // echo $xml;
        // echo "\n\n";



        $xslt = new VerifactuDocumentValidator($xml);
        $xslt->validate();
        $errors = $xslt->getVerifactuErrors();

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(1, $errors);



        // Test deserialization
        $deserialized = Invoice::fromXml($xml);
        $this->assertEquals(0.00, $deserialized->getCuotaTotal());
        $this->assertEquals(100.00, $deserialized->getImporteTotal());
    }

    public function testCreateAndSerializeInvoiceWithDifferentTaxRates(): void
    {
        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-007')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Venta con diferentes tipos impositivos')
            ->setCuotaTotal(31.50)
            ->setImporteTotal(250.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add desglose with multiple tax rates
        $desglose = new Desglose();
        $desglose->setDesgloseIVA([
            [
                'Impuesto' => '01',
                'ClaveRegimen' => '01',
                'CalificacionOperacion' => 'S1',
                'BaseImponibleOimporteNoSujeto' => 100.00,
                'TipoImpositivo' => 21.00,
                'CuotaRepercutida' => 21.00
            ],
            [
                'Impuesto' => '01',
                'ClaveRegimen' => '01',
                'CalificacionOperacion' => 'S1',
                'BaseImponibleOimporteNoSujeto' => 150.00,
                'TipoImpositivo' => 7.00,
                'CuotaRepercutida' => 10.50
            ]
        ]);
        $invoice->setDesglose($desglose);

        // Add encadenamiento (required)
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        // Add sistema informatico
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001')
            ->setTipoUsoPosibleSoloVerifactu('S')
            ->setTipoUsoPosibleMultiOT('S')
            ->setIndicadorMultiplesOT('S');
        $invoice->setSistemaInformatico($sistema);

        // Generate XML string
        $xml = $invoice->toXmlString();

        // Debug output
        // echo "\nGenerated XML:\n";
        // echo $xml;
        // echo "\n\n";


        $xslt = new VerifactuDocumentValidator($xml);
        $xslt->validate();
        $errors = $xslt->getVerifactuErrors();

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(1, $errors);


        // Test deserialization
        $deserialized = Invoice::fromXml($xml);
        $this->assertEquals(31.50, $deserialized->getCuotaTotal());
        $this->assertEquals(250.00, $deserialized->getImporteTotal());
    }

    public function testCreateAndSerializeInvoiceWithSubsequentChain(): void
    {
        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-008')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Factura con encadenamiento posterior')
            ->setCuotaTotal(21.00)
            ->setImporteTotal(100.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add desglose with proper structure
        $desglose = new Desglose();
        $desglose->setDesgloseIVA([
            'Impuesto' => '01',
            'ClaveRegimen' => '01',
            'CalificacionOperacion' => 'S1',
            'BaseImponible' => 100.00,
            'TipoImpositivo' => 21,
            'Cuota' => 21.00
        ]);
        $invoice->setDesglose($desglose);

        // Add encadenamiento with subsequent chain
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        // Add sistema informatico with all required fields
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001')
            ->setTipoUsoPosibleSoloVerifactu('S')
            ->setTipoUsoPosibleMultiOT('S')
            ->setIndicadorMultiplesOT('S');
        $invoice->setSistemaInformatico($sistema);

        // Generate XML string
        $xml = $invoice->toXmlString();

        // Debug output
        // echo "\nGenerated XML:\n";
        // echo $xml;
        // echo "\n\n";


        $xslt = new VerifactuDocumentValidator($xml);
        $xslt->validate();
        $errors = $xslt->getVerifactuErrors();

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(1, $errors);


        // Test deserialization
        $deserialized = Invoice::fromXml($xml);
        $this->assertEquals('S', $deserialized->getEncadenamiento()->getPrimerRegistro());
    }


    public function testInvalidTipoFacturaThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TipoFactura value');

        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-016')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('INVALID'); // This should throw the exception immediately
    }

    public function testInvalidTipoRectificativaThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-017')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('R1')
            ->setTipoRectificativa('INVALID') // Invalid type
            ->setDescripcionOperacion('Rectificación inválida')
            ->setCuotaTotal(-21.00)
            ->setImporteTotal(-100.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add sistema informatico
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001');
        $invoice->setSistemaInformatico($sistema);

        $invoice->toXmlString();
    }


    public function testInvalidNIFFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-019')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Factura con NIF inválido')
            ->setCuotaTotal(21.00)
            ->setImporteTotal(100.00)
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add emitter with invalid NIF
        $emisor = new PersonaFisicaJuridica();
        $emisor
            ->setNif('INVALID_NIF')
            ->setRazonSocial('Empresa Ejemplo SL');
        $invoice->setTercero($emisor);

        // Add sistema informatico
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001');
        $invoice->setSistemaInformatico($sistema);

        $invoice->toXmlString();
    }

    public function testInvalidAmountFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $invoice = new Invoice();
        $invoice
            ->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('FAC-2023-020')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Empresa Ejemplo SL')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Factura con importe inválido')
            ->setCuotaTotal(21.00)
            ->setImporteTotal('INVALID') // Invalid format
            ->setFechaHoraHusoGenRegistro('2023-01-01T12:00:00')
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add sistema informatico
        $sistema = new SistemaInformatico();
        $sistema
            ->setNombreRazon('Sistema de Facturación')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('SistemaFacturacion')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('INST-001');
        $invoice->setSistemaInformatico($sistema);

        $invoice->toXmlString();
    }

    public function testInvalidSchemaThrowsException(): void
    {
        $this->expectException(\DOMException::class);

        $invoice = new Invoice();
        $invoice->setIdVersion('1.0')
            ->setIdFactura((new \App\Services\EDocument\Standards\Verifactu\Models\IDFactura())
                ->setIdEmisorFactura('B12345678')
                ->setNumSerieFactura('TEST123')
                ->setFechaExpedicionFactura('01-01-2023'))
            ->setNombreRazonEmisor('Test Company')
            ->setTipoFactura('F1')
            ->setDescripcionOperacion('Test Operation')
            ->setCuotaTotal(100.00)
            ->setImporteTotal(121.00)
            ->setFechaHoraHusoGenRegistro(date('Y-m-d\TH:i:s'))
            ->setTipoHuella('01')
            ->setHuella('abc123...');

        // Add required sistema informatico with valid values
        $sistema = new SistemaInformatico();
        $sistema->setNombreRazon('Test System')
            ->setNif('B12345678')
            ->setNombreSistemaInformatico('Test Software')
            ->setIdSistemaInformatico('01')
            ->setVersion('1.0')
            ->setNumeroInstalacion('001');
        $invoice->setSistemaInformatico($sistema);

        // Add required desglose with DetalleDesglose
        $desglose = new Desglose();
        $detalleDesglose = new DetalleDesglose();
        $detalleDesglose->setDesgloseIVA([
            'Impuesto' => '01',
            'ClaveRegimen' => '01',
            'BaseImponible' => 100.00,
            'TipoImpositivo' => 21.00,
            'Cuota' => 21.00
        ]);
        $desglose->setDetalleDesglose($detalleDesglose);
        $invoice->setDesglose($desglose);

        // Add required encadenamiento
        $encadenamiento = new Encadenamiento();
        $encadenamiento->setPrimerRegistro('S');
        $invoice->setEncadenamiento($encadenamiento);

        // Generate valid XML first
        $validXml = $invoice->toXmlString();

        // Create a new document with the valid XML
        $doc = new \DOMDocument();
        $doc->loadXML($validXml);

        // Add an invalid element to trigger schema validation error
        $invalidElement = $doc->createElementNS('https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd', 'sum1:InvalidElement');
        $invalidElement->textContent = 'test';
        $doc->documentElement->appendChild($invalidElement);

        // Try to validate the invalid XML using our validateXml method
        $reflectionClass = new \ReflectionClass(Invoice::class);
        $validateXmlMethod = $reflectionClass->getMethod('validateXml');
        $validateXmlMethod->setAccessible(true);
        $validateXmlMethod->invoke(new Invoice(), $doc);

        $xslt = new VerifactuDocumentValidator($validXml);
        $xslt->validate();

        $this->assertCount(1, $xslt->getVerifactuErrors());
    }

    protected function assertXmlEquals(string $expectedXml, string $actualXml): void
    {
        $this->assertEquals(
            $this->normalizeXml($expectedXml),
            $this->normalizeXml($actualXml)
        );
    }

    protected function normalizeXml(string $xml): string
    {
        $doc = new \DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        if (!$doc->loadXML($xml)) {
            throw new \DOMException('Failed to load XML in normalizeXml');
        }
        return $doc->saveXML();
    }

    protected function assertValidatesAgainstXsd(string $xml, string $xsdPath): void
    {
        try {
            $doc = new \DOMDocument();
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = true;
            if (!$doc->loadXML($xml, LIBXML_NOBLANKS)) {
                throw new \DOMException('Failed to load XML in assertValidatesAgainstXsd');
            }

            libxml_use_internal_errors(true);
            $result = $doc->schemaValidate($xsdPath);
            if (!$result) {
                foreach (libxml_get_errors() as $error) {
                }
                libxml_clear_errors();
            }

            $this->assertTrue(
                $result,
                'XML does not validate against XSD schema'
            );
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function getTestXsdPath(): string
    {
        return __DIR__ . '/../schema/SuministroInformacion.xsd';
    }
}
