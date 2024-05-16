<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\EDoc\FatturaPA\Body;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Attributes\Validation\Regex;

class DatiRitenuta extends Data
{
      //string 4 char options
      #[Size(4)]
      public string $TipoRitenuta;
      
      //float 2 decimal
      #[Regex('/^[\-]?[0-9]{1,11}\.[0-9]{2}$/')]
      public float $ImportoRitenuta;

      // <xs:restriction base="xs:decimal">
      // <xs:maxInclusive value="100.00" />
      // <xs:pattern value="/^[0-9]{1,3}\.[0-9]{2}$/" />
      #[Regex('/^[\-]?[0-9]{1,11}\.[0-9]{2}$/')]
      public float $AliquotaRitenuta;

      /*
      <xs:enumeration value="A" />
      <xs:enumeration value="B" />
      <xs:enumeration value="C" />
      <xs:enumeration value="D" />
      <xs:enumeration value="E" />
      <xs:enumeration value="G" />
      <xs:enumeration value="H" />
      <xs:enumeration value="I" />
      <xs:enumeration value="L" />
      <xs:enumeration value="M" />
      <xs:enumeration value="N" />
      <xs:enumeration value="O" />
      <xs:enumeration value="P" />
      <xs:enumeration value="Q" />
      <xs:enumeration value="R" />
      <xs:enumeration value="S" />
      <xs:enumeration value="T" />
      <xs:enumeration value="U" />
      <xs:enumeration value="V" />
      <xs:enumeration value="W" />
      <xs:enumeration value="X" />
      <xs:enumeration value="Y" />
<!-- IL CODICE SEGUENTE (Z) NON SARA' PIU' VALIDO PER LE FATTURE EMESSE A PARTIRE DAL PRIMO GENNAIO 2021-->
      <xs:enumeration value="Z" />
      <xs:enumeration value="L1" />
      <xs:enumeration value="M1" />
      <xs:enumeration value="M2" />
      <xs:enumeration value="O1" />
      <xs:enumeration value="V1" />
      <xs:enumeration value="ZO" />
      */
      #[Max(2)]
      public string $CausalePagamento;
      
}
