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

namespace App\Services\EDocument\Gateway\Storecove;

class StorecoveRouter
{  
    private array $routing_rules = [
        "US" => [
            ["B","DUNS, GLN, LEI","US:EIN","DUNS, GLN, LEI"],
            // ["B","DUNS, GLN, LEI","US:SSN","DUNS, GLN, LEI"],
        ],
        "CA" => ["B","CA:CBN",false,"CA:CBN"],
        "MX" => ["B","MX:RFC",false,"MX:RFC"],
        "AU" => ["B+G","AU:ABN",false,"AU:ABN"],
        "NZ" => ["B+G","GLN","NZ:GST","GLN"],
        "CH" => ["B+G","CH:UIDB","CH:VAT","CH:UIDB"],
        "IS" => ["B+G","IS:KTNR","IS:VAT","IS:KTNR"],
        "LI" => ["B+G","","LI:VAT","LI:VAT"],
        "NO" => ["B+G","NO:ORG","NO:VAT","NO:ORG"],
        "AD" => ["B+G","","AD:VAT","AD:VAT"],
        "AL" => ["B+G","","AL:VAT","AL:VAT"],
        "AT" => [
            ["G","AT:GOV",false,"9915:b"],
            ["B","","AT:VAT","AT:VAT"],
        ],
        "BA" => ["B+G","","BA:VAT","BA:VAT"],
        "BE" => ["B+G","BE:EN","BE:VAT","BE:EN"],
        "BG" => ["B+G","","BG:VAT","BG:VAT"],
        "CY" => ["B+G","","CY:VAT","CY:VAT"],
        "CZ" => ["B+G","","CZ:VAT","CZ:VAT"],
        "DE" => [
            ["G","DE:LWID",false,"DE:LWID"],
            ["B","","DE:VAT","DE:VAT"],
        ],
        "DK" => ["B+G","DK:DIGST","DK:ERST","DK:DIGST"],
        "EE" => ["B+G","EE:CC","EE:VAT","EE:CC"],
        "ES" => ["B","","ES:VAT","ES:VAT"],
        "FI" => ["B+G","FI:OVT","FI:VAT","FI:OVT"],
        "FR" => [
            ["G","FR:SIRET + customerAssignedAccountIdValue",false,"0009:11000201100044"],
            ["B","FR:SIRENE or FR:SIRET","FR:VAT","FR:SIRENE or FR:SIRET"],
        ],
        "GR" => ["B+G","","GR:VAT","GR:VAT"],
        "HR" => ["B+G","","HR:VAT","HR:VAT"],
        "HU" => ["B+G","","HU:VAT","HU:VAT"],
        "IE" => ["B+G","","IE:VAT","IE:VAT"],
        "IT" => [
            ["G","","IT:IVA","IT:CUUO"], // (Peppol)
            ["B","","IT:IVA","IT:CUUO"], // (SDI)
            // ["B","","IT:CF","IT:CUUO"], // (SDI)
            ["C","","IT:CF","Email"],// (SDI)
            ["G","","IT:IVA","IT:CUUO"],// (SDI)
        ],
        "LT" => ["B+G","LT:LEC","LT:VAT","LT:LEC"],
        "LU" => ["B+G","LU:MAT","LU:VAT","LU:VAT"],
        "LV" => ["B+G","","LV:VAT","LV:VAT"],
        "MC" => ["B+G","","MC:VAT","MC:VAT"],
        "ME" => ["B+G","","ME:VAT","ME:VAT"],
        "MK" => ["B+G","","MK:VAT","MK:VAT"],
        "MT" => ["B+G","","MT:VAT","MT:VAT"],
        "NL" => ["G","NL:OINO",false,"NL:OINO"],
        "NL" => ["B","NL:KVK","NL:VAT","NL:KVK or NL:VAT"],
        "PL" => ["G+B","","PL:VAT","PL:VAT"],
        "PT" => ["G+B","","PT:VAT","PT:VAT"],
        "RO" => ["G+B","","RO:VAT","RO:VAT"],
        "RS" => ["G+B","","RS:VAT","RS:VAT"],
        "SE" => ["G+B","SE:ORGNR","SE:VAT","SE:ORGNR"],
        "SI" => ["G+B","","SI:VAT","SI:VAT"],
        "SK" => ["G+B","","SK:VAT","SK:VAT"],
        "SM" => ["G+B","","SM:VAT","SM:VAT"],
        "TR" => ["G+B","","TR:VAT","TR:VAT"],
        "VA" => ["G+B","","VA:VAT","VA:VAT"],
        "IN" => ["B","","IN:GSTIN","Email"],
        "JP" => ["B","JP:SST","JP:IIN","JP:SST"],
        "MY" => ["B","MY:EIF","MY:TIN","MY:EIF"],
        "SG" => [
            ["G","SG:UEN",false,"0195:SGUENT08GA0028A"],
            ["B","SG:UEN","SG:GST","SG:UEN"],
        ],
        "GB" => ["B","","GB:VAT","GB:VAT"],
        "SA" => ["B","","SA:TIN","Email"],
        "Other" => ["B","DUNS, GLN, LEI",false,"DUNS, GLN, LEI"],
    ];

    public function __construct()
    {
    }
    
    /**
     * Return the routing code based on country and entity classification
     *
     * @param  string $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveRouting(string $country, ?string $classification = 'business'): string
    {
        $rules = $this->routing_rules[$country];
        
        if(is_array($rules) && !is_array($rules[0])) {
            return $rules[3];
        }
    
        $code = 'B';
        
        match($classification) {
            "business" => $code = "B",
            "government" => $code = "G",
            "individual" => $code = "C",
            default => $code = "B",
        };

        foreach($rules as $rule) {
            if(stripos($rule[0], $code) !== false) {
                return $rule[3];
            }
        }

        return $rules[0][3];
    }
    
    /**
     * resolveTaxScheme
     *
     * @param  string $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveTaxScheme(string $country, ?string $classification = "business"): string
    {
                
        $rules = isset($this->routing_rules[$country]) ? $this->routing_rules[$country] : [false, false, false, false];

        $code = "B";

        match($classification) {
            "business" => $code = "B",
            "government" => $code = "G",
            "individual" => $code = "C",
            default => $code = "B",
        };

        //single array
        if(is_array($rules) && !is_array($rules[0])) {
            return $rules[2];
        }

        foreach($rules as $rule) {
            if(stripos($rule[0], $code) !== false) {
                return $rule[2];
            }
        }

        return $rules[0][2];
    }
}