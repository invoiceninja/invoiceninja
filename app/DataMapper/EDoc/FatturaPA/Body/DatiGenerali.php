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
use Spatie\LaravelData\Optional;
use App\DataMapper\EDoc\FatturaPA\DatiTrasporto;
use App\DataMapper\EDoc\FatturaPA\Generics\DatiDDT;
use App\DataMapper\EDoc\FatturaPA\Generics\DatiSAL;
use App\DataMapper\EDoc\FatturaPA\FatturaPrincipale;
use App\DataMapper\EDoc\FatturaPA\Generics\DatiDocumentiCorrelatiType;

class DatiGenerali extends Data
{

public DatiGeneraliDocumento $DatiGeneraliDocumento;           
public DatiDocumentiCorrelatiType|Optional $DatiOrdineAcquisto;                  
public DatiDocumentiCorrelatiType|Optional $DatiContratto;                   
public DatiDocumentiCorrelatiType|Optional $DatiConvenzione;                   
public DatiDocumentiCorrelatiType|Optional $DatiRicezione;                   
public DatiDocumentiCorrelatiType|Optional $DatiFattureCollegate;                   
public DatiSAL|Optional $DatiSAL;                   
public DatiDDT|Optional $DatiDDT;                   
public DatiTrasporto|Optional $DatiTrasporto;                   
public FatturaPrincipale|Optional $FatturaPrincipale;                   

}


