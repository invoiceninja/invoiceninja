<?php

namespace App\Services\EDocument\Standards\FatturaPA\Enums;

enum ModalitaPagamento: string
{
    case MP01_CASH = 'MP01';
    case MP02_CHECK = 'MP02';
    case MP03_CERTIFIED_CHECK = 'MP03';
    case MP04_CASH_AT_TREASURY = 'MP04';
    case MP05_BANK_TRANSFER = 'MP05';
    case MP06_BILL_OF_EXCHANGE = 'MP06';
    case MP07_BANK_SLIP = 'MP07';
    case MP08_PAYMENT_CARD = 'MP08';
    case MP09_RID = 'MP09';
    case MP10_RID_UTILITIES = 'MP10';
    case MP11_FAST_RID = 'MP11';
    case MP12_RIBA = 'MP12';
    case MP13_MAV = 'MP13';
    case MP14_TREASURY_RECEIPT = 'MP14';
    case MP15_INTERACCOUNT_TRANSFER = 'MP15';
    case MP16_BANK_DIRECT_DEBIT = 'MP16';
    case MP17_POSTAL_DIRECT_DEBIT = 'MP17';
    case MP18_POSTAL_ACCOUNT_SLIP = 'MP18';
    case MP19_SEPA_DIRECT_DEBIT = 'MP19';
    case MP20_SEPA_DIRECT_DEBIT_CORE = 'MP20';
    case MP21_SEPA_DIRECT_DEBIT_B2B = 'MP21';
    case MP22_WITHHOLDING_ON_COLLECTED_AMOUNTS = 'MP22';
    case MP23_PAGOPA = 'MP23';

    public function getDescription(): string
    {
        return match ($this) {
            self::MP01_CASH => 'Contanti',
            self::MP02_CHECK => 'Assegno',
            self::MP03_CERTIFIED_CHECK => 'Assegno circolare',
            self::MP04_CASH_AT_TREASURY => 'Contanti presso Tesoreria',
            self::MP05_BANK_TRANSFER => 'Bonifico',
            self::MP06_BILL_OF_EXCHANGE => 'Vaglia cambiario',
            self::MP07_BANK_SLIP => 'Bollettino bancario',
            self::MP08_PAYMENT_CARD => 'Carta di pagamento',
            self::MP09_RID => 'RID',
            self::MP10_RID_UTILITIES => 'RID utenze',
            self::MP11_FAST_RID => 'RID veloce',
            self::MP12_RIBA => 'RIBA',
            self::MP13_MAV => 'MAV',
            self::MP14_TREASURY_RECEIPT => 'Quietanza erario',
            self::MP15_INTERACCOUNT_TRANSFER => 'Giroconto su conti di contabilità speciale',
            self::MP16_BANK_DIRECT_DEBIT => 'Domiciliazione bancaria',
            self::MP17_POSTAL_DIRECT_DEBIT => 'Domiciliazione postale',
            self::MP18_POSTAL_ACCOUNT_SLIP => 'Bollettino di c/c postale',
            self::MP19_SEPA_DIRECT_DEBIT => 'SEPA Direct Debit',
            self::MP20_SEPA_DIRECT_DEBIT_CORE => 'SEPA Direct Debit CORE',
            self::MP21_SEPA_DIRECT_DEBIT_B2B => 'SEPA Direct Debit B2B',
            self::MP22_WITHHOLDING_ON_COLLECTED_AMOUNTS => 'Trattenuta su somme già riscosse',
            self::MP23_PAGOPA => 'PagoPA',
        };
    }

    public static function getByPaymentType(int $type): ?self
    {
        return match ($type) {
            1 => self::MP05_BANK_TRANSFER, // Bank Transfer
            28 => self::MP05_BANK_TRANSFER, // Sofort
            2 => self::MP01_CASH, // Cash
            3 => self::MP16_BANK_DIRECT_DEBIT, // Debit
            5 => self::MP08_PAYMENT_CARD, // Visa Card
            6 => self::MP08_PAYMENT_CARD, // MasterCard
            7 => self::MP08_PAYMENT_CARD, // American Express
            8 => self::MP08_PAYMENT_CARD, // Discover Card
            9 => self::MP08_PAYMENT_CARD, // Diners Card
            10 => self::MP08_PAYMENT_CARD, // EuroCard
            11 => self::MP08_PAYMENT_CARD, // Nova
            13 => self::MP08_PAYMENT_CARD, // PayPal
            14 => self::MP08_PAYMENT_CARD, // Google Wallet
            20 => self::MP08_PAYMENT_CARD, // Maestro
            12 => self::MP08_PAYMENT_CARD, // Credit Card Other
            15 => self::MP02_CHECK, // Check
            29 => self::MP19_SEPA_DIRECT_DEBIT, // SEPA
            default => null,
        };
    }

    public function getVersion(): string
    {
        return match ($this) {
            self::MP23_PAGOPA => 'V.202X',
            default => 'V.20XX',
        };
    }
}
