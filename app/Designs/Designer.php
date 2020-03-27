<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Designs;

use App\Models\Company;
use App\Models\Invoice;

class Designer
{
    public $design;

    protected $input_variables;

    protected $exported_variables;

    protected $html;

    protected $entity_string;

    protected $entity;

    private static $custom_fields = [
        'invoice1',
        'invoice2',
        'invoice3',
        'invoice4',
        'surcharge1',
        'surcharge2',
        'surcharge3',
        'surcharge4',
        'client1',
        'client2',
        'client3',
        'client4',
        'contact1',
        'contact2',
        'contact3',
        'contact4',
        'company1',
        'company2',
        'company3',
        'company4',
    ];

    public function __construct($entity, $design, $input_variables, $entity_string)
    {
        $this->entity = $entity;

        $this->design = $design->design;

        $this->input_variables = json_decode(json_encode($input_variables), 1);

        $this->entity_string = $entity_string;
    }

    /**
     * Returns the design
     * formatted HTML
     * @return string The HTML design built
     */
    public function build():Designer
    {
        $this->setHtml()
            ->exportVariables()
            ->setDesign($this->getSection('includes'))
            ->setDesign($this->getSection('header'))
            ->setDesign($this->getSection('body'))
            ->setDesign($this->getSection('footer'));

        return $this;
    }

    public function init()
    {
        $this->setHtml()
             ->exportVariables();

        return $this;
    }

    public function getIncludes()
    {
        return $this->getSection('includes');
    }

    public function getHeader()
    {
        return $this->getSection('header');
    }

    public function getFooter()
    {
        return $this->getSection('footer');
    }

    public function getBody()
    {
        return $this->getSection('body');
    }

    public function getHtml():string
    {
        return $this->html;
    }

    public function setHtml()
    {
        $this->html =  '';

        return $this;
    }

    private function setDesign($section)
    {
        $this->html .= $section;

        return $this;
    }

    /**
     * Returns the template section on with the
     * stacked variables replaced with single variables.
     *
     * @param  string $section the method name to be executed ie header/body/table/footer
     * @return string The HTML of the template section
     */
    public function getSection($section):string
    {
        return str_replace(array_keys($this->exported_variables), array_values($this->exported_variables), $this->design->{$section});
    }

    private function exportVariables()
    {$s = microtime(true);
        $company = $this->entity->company;
        
        $this->exported_variables['$app_url']			= $this->entity->generateAppUrl();
        $this->exported_variables['$client_details']  	= $this->processVariables($this->input_variables['client_details'], $this->clientDetails($company));
        $this->exported_variables['$company_details'] 	= $this->processVariables($this->input_variables['company_details'], $this->companyDetails($company));
        $this->exported_variables['$company_address'] 	= $this->processVariables($this->input_variables['company_address'], $this->companyAddress($company));

        if ($this->entity_string == 'invoice') {
            $this->exported_variables['$entity_labels']  = $this->processLabels($this->input_variables['invoice_details'], $this->invoiceDetails($company));
            $this->exported_variables['$entity_details'] = $this->processVariables($this->input_variables['invoice_details'], $this->invoiceDetails($company));
        } elseif ($this->entity_string == 'credit') {
            $this->exported_variables['$entity_labels']  = $this->processLabels($this->input_variables['credit_details'], $this->creditDetails($company));
            $this->exported_variables['$entity_details'] = $this->processVariables($this->input_variables['credit_details'], $this->creditDetails($company));
        } elseif ($this->entity_string == 'quote') {
            $this->exported_variables['$entity_labels']  = $this->processLabels($this->input_variables['quote_details'], $this->quoteDetails($company));
            $this->exported_variables['$entity_details'] = $this->processVariables($this->input_variables['quote_details'], $this->quoteDetails($company));
        }

        $this->exported_variables['$product_table_header']= $this->entity->buildTableHeader($this->input_variables['product_columns']);
        $this->exported_variables['$product_table_body']  = $this->entity->buildTableBody($this->input_variables['product_columns'], $this->design->product, '$product');
        $this->exported_variables['$task_table_header']   = $this->entity->buildTableHeader($this->input_variables['task_columns']);
        $this->exported_variables['$task_table_body']     = $this->entity->buildTableBody($this->input_variables['task_columns'], $this->design->task, '$task');

        if (strlen($this->exported_variables['$task_table_body']) == 0) {
            $this->exported_variables['$task_table_header'] = '';
        }

        if (strlen($this->exported_variables['$product_table_body']) == 0) {
            $this->exported_variables['$product_table_header'] = '';
        }
        \Log::error("Exporting variables took = ".(microtime(true)-$s));
        return $this;
    }

    private function processVariables($input_variables, $variables):string
    {
        $output = '';

        foreach (array_values($input_variables) as $value) {

            if(array_key_exists($value, $variables))
                $output .= $variables[$value];
        }

        return $output;
    }

    private function processLabels($input_variables, $variables):string
    {
        $output = '';

        foreach (array_keys($input_variables) as $value) {

            if(array_key_exists($value, $variables)){
                $tmp = str_replace("</span>", "_label</span>", $variables[$value]);
            
                $output .= $tmp;
            }
            
        }

        return $output;
    }

    private function clientDetails(Company $company)
    {
        $data = [
            '$client.name'              => '<p>$client.name</p>',
            '$client.id_number'         => '<p>$client.id_number</p>',
            '$client.vat_number'        => '<p>$client.vat_number</p>',
            '$client.address1'          => '<p>$client.address1</p>',
            '$client.address2'          => '<p>$client.address2</p>',
            '$client.city_state_postal' => '<p>$client.city_state_postal</p>',
            '$client.postal_city_state' => '<p>$client.postal_city_state</p>',
            '$client.country'           => '<p>$client.country</p>',
            '$contact.email'             => '<p>$client.email</p>',
            '$client.custom1'           => '<p>$client.custom1</p>',
            '$client.custom2'           => '<p>$client.custom2</p>',
            '$client.custom3'           => '<p>$client.custom3</p>',
            '$client.custom4'           => '<p>$client.custom4</p>',
            '$contact.contact1'          => '<p>$contact.custom1</p>',
            '$contact.contact2'          => '<p>$contact.custom2</p>',
            '$contact.contact3'          => '<p>$contact.custom3</p>',
            '$contact.contact4'          => '<p>$contact.custom4</p>',
        ];

        return $this->processCustomFields($company, $data);
    }

    private function companyDetails(Company $company)
    {
        $data = [
            '$company.company_name' => '<span>$company.company_name</span>',
            '$company.id_number'    => '<span>$company.id_number</span>',
            '$company.vat_number'   => '<span>$company.vat_number</span>',
            '$company.website'      => '<span>$company.website</span>',
            '$company.email'        => '<span>$company.email</span>',
            '$company.phone'        => '<span>$company.phone</span>',
            '$company.company1'     => '<span>$company1</span>',
            '$company.company2'     => '<span>$company2</span>',
            '$company.company3'     => '<span>$company3</span>',
            '$company.company4'     => '<span>$company4</span>',
        ];

        return $this->processCustomFields($company, $data);
    }

    private function companyAddress(Company $company)
    {
        $data = [
            '$company.address1'          => '<span>$company.address1</span>',
            '$company.address2'          => '<span>$company.address2</span>',
            '$company.city_state_postal' => '<span>$company.city_state_postal</span>',
            '$company.postal_city_state' => '<span>$company.postal_city_state</span>',
            '$company.country'           => '<span>$company.country</span>',
            '$company.company1'          => '<span>$company1</span>',
            '$company.company2'          => '<span>$company2</span>',
            '$company.company3'          => '<span>$company3</span>',
            '$company.company4'          => '<span>$company4</span>',
        ];

        return $this->processCustomFields($company, $data);
    }

    private function invoiceDetails(Company $company)
    {
        $data = [
            '$invoice.invoice_number' => '<span>$invoice.number_label</span><span>$invoice.number</span>',
            '$invoice.po_number'      => '<span>$invoice.po_number_label</span><span>$invoice.po_number</span>',
            '$invoice.invoice_date'   => '<span>$invoice.date_label</span><span>$invoice.date</span>',
            '$invoice.due_date'       => '<span>$invoice.due_date_label</span><span>$invoice.due_date</span>',
            '$invoice.balance_due'    => '<span>$invoice.balance_due_label</span><span>$invoice.balance_due</span>',
            '$invoice.invoice_total'  => '<span>$invoice.total_label</span><span>$invoice.total</span>',
            '$invoice.partial_due'    => '<span>$invoice.partial_due_label</span><span>$invoice.partial_due</span>',
            '$invoice.invoice1'       => '<span>$invoice1_label</span><span>$invoice1</span>',
            '$invoice.invoice2'       => '<span>$invoice2_label</span><span>$invoice2</span>',
            '$invoice.invoice3'       => '<span>$invoice3_label</span><span>$invoice3</span>',
            '$invoice.invoice4'       => '<span>$invoice4_label</span><span>$invoice4</span>',
            '$invoice.surcharge1'     => '<span>$surcharge1_label</span><span>$surcharge1</span>',
            '$invoice.surcharge2'     => '<span>$surcharge2_label</span><span>$surcharge2</span>',
            '$invoice.surcharge3'     => '<span>$surcharge3_label</span><span>$surcharge3</span>',
            '$invoice.surcharge4'     => '<span>$surcharge4_label</span><span>$surcharge4</span>',
        ];

        return $this->processCustomFields($company, $data);
    }

    private function quoteDetails(Company $company)
    {
        $data = [
            '$quote.quote_number' 	=> '<span>$quote.number</span>',
            '$quote.po_number'      => '<span>$quote.po_number</span>',
            '$quote.quote_date'     => '<span>$quote.date</span>',
            '$quote.valid_until'    => '<span>$quote.valid_until</span>',
            '$quote.balance_due'    => '<span>$quote.balance_due</span>',
            '$quote.quote_total'  	=> '<span>$quote.total</span>',
            '$quote.partial_due'    => '<span>$quote.partial_due</span>',
            '$quote.quote1'       	=> '<span>$quote1</span>',
            '$quote.quote2'       	=> '<span>$quote2</span>',
            '$quote.quote3'       	=> '<span>$quote3</span>',
            '$quote.quote4'         => '<span>$quote4</span>',
            '$quote.surcharge1'     => '<span>$surcharge1</span>',
            '$quote.surcharge2'     => '<span>$surcharge2</span>',
            '$quote.surcharge3'     => '<span>$surcharge3</span>',
            '$quote.surcharge4'     => '<span>$surcharge4</span>',
        ];

        return $this->processCustomFields($company, $data);
    }

    private function creditDetails(Company $company)
    {
        $data = [
            '$credit.credit_number'  => '<span>$credit.number</span>',
            '$credit.po_number'      => '<span>$credit.po_number</span>',
            '$credit.credit_date'    => '<span>$credit.date</span>',
            '$credit.credit_balance' => '<span>$credit.balance</span>',
            '$credit.credit_amount'  => '<span>$credit.amount</span>',
            '$credit.partial_due'    => '<span>$credit.partial_due</span>',
            '$credit.credit1'        => '<span>$credit1</span>',
            '$credit.credit2'        => '<span>$credit2</span>',
            '$credit.credit3'        => '<span>$credit3</span>',
            '$credit.credit4'        => '<span>$credit4</span>',
            '$credit.surcharge1'     => '<span>$surcharge1</span>',
            '$credit.surcharge2'     => '<span>$surcharge2</span>',
            '$credit.surcharge3'     => '<span>$surcharge3</span>',
            '$credit.surcharge4'     => '<span>$surcharge4</span>',
        ];

        return $this->processCustomFields($company, $data);
    }

    private function processCustomFields(Company $company, $data)
    {
        $custom_fields = $company->custom_fields;

        if (!$custom_fields) {
            return $data;
        }

        foreach (self::$custom_fields as $cf) {
            if (!property_exists($custom_fields, $cf) || (strlen($custom_fields->{$cf}) == 0)) {
                unset($data[$cf]);
            }
        }

        return $data;
    }

    // private function processInputVariables($company, $variables)
    // {
    // 	if(is_object($variables))
    // 		$variables = json_decode(json_encode($variables),true);

    // 	$custom_fields = $company->custom_fields;

    // 	$matches = array_intersect(self::$custom_fields, $variables);

    // 	foreach ($matches as $match) {

    // 		if (!property_exists($custom_fields, $match) || (strlen($custom_fields->{$match}) == 0)) {
    // 			foreach ($variables as $key => $value) {
    // 				if ($value == $match) {
    // 					unset($variables[$key]);
    // 				}
    // 			}
    // 		}

    // 	}

    // 	return $variables;

    // }
}
