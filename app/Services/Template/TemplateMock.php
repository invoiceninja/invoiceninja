<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Template;

use App\Models\Company;
use App\Services\Pdf\PdfMock;

class TemplateMock
{
    use MockTrait;
    
    private Company $company;

    public function _invoke(Company $company): array
    {

        $this->company = $company;

        $variables = collect(['invoices', 'quotes', 'credits', 'payments', 'purchase_orders'])->map(function ($type) {
            $this->createVariables($type);
        })->toArray();

        $variables = array_merge($variables, [
            'invoices' => $this->createTemplate('invoices'),
            'quotes' => $this->createTemplate('quotes'),
            'credits' => $this->createTemplate('credits'),
            'tasks' => $this->createTemplate('tasks'),
            'projects' => $this->createTemplate('projects'),
            'payments' => $this->createTemplate('payments'),
            'purchase_orders' => $this->createTemplate('purchase_orders'),
        ]);
            
        return $variables;
    }

    /**
     * ['entity_type','design','settings_type','settings']
     *
     * @param string $type
     * @return array
     */
    private function createVariables(string $type): array
    {   
        $data = [
            'entity_type' => rtrim($type,"s"),
            'design' => '',
            'settings_type' => 'company',
            'settings' => $this->company->settings,
        ];

        $mock = (new PdfMock($data, $this->company))->build();

        return [$type => $mock->getStubVariables()];
    }

    private function createTemplate(string $type): array
    {

    }
}