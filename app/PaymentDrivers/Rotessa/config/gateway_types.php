<?php

use App\Models\GatewayType;

return [
    'gateway_types' => [
        GatewayType::BANK_TRANSFER => [
            'refund' => false,
            'token_billing' => true,
            'webhooks' => [],
        ], 
        //GatewayType::BACS => ['refund' => false, 'token_billing' => true, 'webhooks' => []], 
        //GatewayType::DIRECT_DEBIT => ['refund' => false, 'token_billing' => true, 'webhooks' => []], 
        GatewayType::ACSS => ['refund' => false, 'token_billing' => true, 'webhooks' => []]
    ]
];