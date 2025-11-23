<?php

namespace App\Services\EDocument\Standards\Verifactu\Signing;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class SigningService
{
    public function __construct(
        private string $xml,
        private string $private_key,
        private string $certificate
    ) {
    }

    public function sign()
    {
        $doc = new \DOMDocument();
        $doc->loadXML($this->xml);

        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );

        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($this->private_key, false);

        // Attach the certificate (public) to the KeyInfo
        $objDSig->add509Cert($this->certificate, true, false, ['subjectName' => true]);

        $objDSig->sign($objKey);
        $objDSig->appendSignature($doc->documentElement);

        // --- 3. Return signed XML as string ---
        return $doc->saveXML();

    }
}
