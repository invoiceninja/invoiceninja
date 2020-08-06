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
        $div = '
            %s <!-- Placeholder for getSection(footer) -->
            <div class="flex items-center justify-between m-12">
                %s <!-- Placeholder for signature -->
                %s <!-- Placehoder for Invoice Ninja logo -->
            </div>'
        ;

        $signature = '<img class="h-40" src="$contact.signature" />';
        $logo = '<div></div>';

        if (!$this->entity->user->account->isPaid()) {
            $logo = '<img class="h-32" src="$app_url/images/created-by-invoiceninja-new.png" />';
        }

        return sprintf($div, $this->getSection('footer'), $signature, $logo);
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
        return strtr($this->design->{$section}, $this->exported_variables);
        //   return str_replace(array_keys($this->exported_variables), array_values($this->exported_variables), $this->design->{$section});
    }

    private function exportVariables()
    {
        //$s = microtime(true);
        $company = $this->entity->company;
        
        $this->exported_variables['$custom_css']        = $this->entity->generateCustomCSS();
        $this->exported_variables['$app_url']			= $this->entity->generateAppUrl();
        $this->exported_variables['$client_details']  	= $this->processVariables($this->input_variables['client_details'], $this->clientDetails($company));
        $this->exported_variables['$company_details'] 	= $this->processVariables($this->input_variables['company_details'], $this->companyDetails($company));
        $this->exported_variables['$company_address'] 	= $this->processVariables($this->input_variables['company_address'], $this->companyAddress($company));

        if ($this->entity_string == 'invoice') {
            //$this->exported_variables['$entity_labels']  = $this->processLabels($this->input_variables['invoice_details'], $this->invoiceDetails($company));
            $this->exported_variables['$entity_details'] = $this->processVariables($this->input_variables['invoice_details'], $this->invoiceDetails($company));
        } elseif ($this->entity_string == 'credit') {
            //$this->exported_variables['$entity_labels']  = $this->processLabels($this->input_variables['credit_details'], $this->creditDetails($company));
            $this->exported_variables['$entity_details'] = $this->processVariables($this->input_variables['credit_details'], $this->creditDetails($company));
        } elseif ($this->entity_string == 'quote') {
            //$this->exported_variables['$entity_labels']  = $this->processLabels($this->input_variables['quote_details'], $this->quoteDetails($company));
            $this->exported_variables['$entity_details'] = $this->processVariables($this->input_variables['quote_details'], $this->quoteDetails($company));
        } else {
            $this->exported_variables['$entity_details'] = $this->processVariables($this->input_variables['invoice_details'], $this->quoteDetails($company));
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
        return $this;
    }

    private function processVariables($input_variables, $variables):string
    {
        $output = '';

        foreach (array_values($input_variables) as $value) {
            if (array_key_exists($value, $variables)) {
                $output .= $variables[$value];
            }
        }

        return $output;
    }

    private function processLabels($input_variables, $variables):string
    {
        $output = '';

        foreach (array_keys($input_variables) as $value) {
            if (array_key_exists($value, $variables)) {
                //$tmp = str_replace("</span>", "_label</span>", $variables[$value]);
                $tmp = strtr($variables[$value], "</span>", "_label</span>");
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
            '$contact.email'            => '<p>$client.email</p>',
            '$client.custom1'           => '<p>$client.custom1</p>',
            '$client.custom2'           => '<p>$client.custom2</p>',
            '$client.custom3'           => '<p>$client.custom3</p>',
            '$client.custom4'           => '<p>$client.custom4</p>',
            '$contact.contact1'         => '<p>$contact.custom1</p>',
            '$contact.contact2'         => '<p>$contact.custom2</p>',
            '$contact.contact3'         => '<p>$contact.custom3</p>',
            '$contact.contact4'         => '<p>$contact.custom4</p>',
        ];

        return $this->processCustomFields($company, $data);
    }

    private function companyDetails(Company $company)
    {
        $data = [
            '$company.name'         => '<span>$company.name</span>',
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
            '$invoice.number'           => '<span class="flex justify-between items-center"><span>$invoice.number_label:</span><span> $invoice.number</span></span>',
            '$invoice.po_number'        => '<span class="flex justify-between items-center"><span>$invoice.po_number_label:</span><span> $invoice.po_number</span></span>',
            '$invoice.date'             => '<span class="flex justify-between items-center"><span>$invoice.date_label:</span><span> $invoice.date</span></span>',
            '$invoice.due_date'         => '<span class="flex justify-between items-center"><span>$invoice.due_date_label:</span><span> $invoice.due_date</span></span>',
            '$invoice.balance_due'      => '<span class="flex justify-between items-center"><span>$invoice.balance_due_label:</span><span> $invoice.balance_due</span></span>',
            '$invoice.total'            => '<span class="flex justify-between items-center"><span>$invoice.total_label:</span><span> $invoice.total</span></span>',
            '$invoice.partial_due'      => '<span class="flex justify-between items-center"><span>$invoice.partial_due_label:</span><span> $invoice.partial_due</span></span>',
            '$invoice.custom1'          => '<span class="flex justify-between items-center"><span>$invoice1_label:</span><span> $invoice.custom1</span></span>',
            '$invoice.custom2'          => '<span class="flex justify-between items-center"><span>$invoice2_label:</span><span> $invoice.custom2</span></span>',
            '$invoice.custom3'          => '<span class="flex justify-between items-center"><span>$invoice3_label:</span><span> $invoice.custom3</span></span>',
            '$invoice.custom4'          => '<span class="flex justify-between items-center"><span>$invoice4_label:</span><span> $invoice.custom4</span></span>',
            '$surcharge1'               => '<span class="flex justify-between items-center"><span>$surcharge1_label:</span><span> $surcharge1</span></span>',
            '$surcharge2'               => '<span class="flex justify-between items-center"><span>$surcharge2_label:</span><span> $surcharge2</span></span>',
            '$surcharge3'               => '<span class="flex justify-between items-center"><span>$surcharge3_label:</span><span> $surcharge3</span></span>',
            '$surcharge4'               => '<span class="flex justify-between items-center"><span>$surcharge4_label:</span><span> $surcharge4</span></span>',

        ];

        return $this->processCustomFields($company, $data);
    }

    private function quoteDetails(Company $company)
    {
        $data = [
            '$quote.quote_number' 	=> '<span class="flex flex-wrap justify-between items-center"><span>$quote.number_label:</span><span> $quote.number</span></span>',
            '$quote.po_number'      => '<span class="flex flex-wrap justify-between items-center"><span>$quote.po_number_label:</span><span> $quote.po_number</span></span>',
            '$quote.quote_date'     => '<span class="flex flex-wrap justify-between items-center"><span>$quote.date_label:</span><span> $quote.date</span></span>',
            '$quote.valid_until'    => '<span class="flex flex-wrap justify-between items-center"><span>$quote.valid_until_label:</span><span> $quote.valid_until</span></span>',
            '$quote.balance_due'    => '<span class="flex flex-wrap justify-between items-center"><span>$quote.balance_due_label:</span><span> $quote.balance_due</span></span>',
            '$quote.quote_total'  	=> '<span class="flex flex-wrap justify-between items-center"><span>$quote.total_label:</span><span> $quote.total</span></span>',
            '$quote.partial_due'    => '<span class="flex flex-wrap justify-between items-center"><span>$quote.partial_due_label:</span><span> $quote.partial_due</span></span>',
            '$quote.custom1'       	=> '<span class="flex flex-wrap justify-between items-center"><span>$quote.custom1_label:</span><span> $quote.custom1</span></span>',
            '$quote.custom2'       	=> '<span class="flex flex-wrap justify-between items-center"><span>$quote.custom2_label:</span><span> $quote.custom2</span></span>',
            '$quote.custom3'       	=> '<span class="flex flex-wrap justify-between items-center"><span>$quote.custom3_label:</span><span> $quote.custom3</span></span>',
            '$quote.custom4'        => '<span class="flex flex-wrap justify-between items-center"><span>$quote.custom4_label:</span><span> $quote.custom4</span></span>',
            '$quote.surcharge1'     => '<span class="flex flex-wrap justify-between items-center"><span>$surcharge1_label:</span><span> $surcharge1</span></span>',
            '$quote.surcharge2'     => '<span class="flex flex-wrap justify-between items-center"><span>$surcharge2_label:</span><span> $surcharge2</span></span>',
            '$quote.surcharge3'     => '<span class="flex flex-wrap justify-between items-center"><span>$surcharge3_label:</span><span> $surcharge3</span></span>',
            '$quote.surcharge4'     => '<span class="flex flex-wrap justify-between items-center"><span>$surcharge4_label:</span><span> $surcharge4</span></span>',

        ];

        return $this->processCustomFields($company, $data);
    }

    private function creditDetails(Company $company)
    {
        $data = [
            '$credit.credit_number'  => '<span class="flex justify-between items-center">$credit.number_label<span></span><span>$credit.number</span></span>',
            '$credit.po_number'      => '<span class="flex justify-between items-center">$credit.po_number_label<span></span><span>$credit.po_number</span></span>',
            '$credit.credit_date'    => '<span class="flex justify-between items-center">$credit.date_label<span></span><span>$credit.date</span></span>',
            '$credit.credit_balance' => '<span class="flex justify-between items-center">$credit.balance_label<span></span><span>$credit.balance</span></span>',
            '$credit.credit_amount'  => '<span class="flex justify-between items-center">$credit.amount_label<span></span><span>$credit.amount</span></span>',
            '$credit.partial_due'    => '<span class="flex justify-between items-center">$credit.partial_due_label<span></span><span>$credit.partial_due</span></span>',
            '$credit.custom1'        => '<span class="flex justify-between items-center">$credit.custom1_label<span></span><span>$credit.custom1</span></span>',
            '$credit.custom2'        => '<span class="flex justify-between items-center">$credit.custom2_label<span></span><span>$credit.custom2</span></span>',
            '$credit.custom3'        => '<span class="flex justify-between items-center">$credit.custom3_label<span></span><span>$credit.custom3</span></span>',
            '$credit.custom4'        => '<span class="flex justify-between items-center">$credit.custom4_label<span></span><span>$credit.custom4</span></span>',
            '$credit.surcharge1'     => '<span class="flex justify-between items-center">$surcharge1_label<span></span><span>$surcharge1_label: $surcharge1</span></span>',
            '$credit.surcharge2'     => '<span class="flex justify-between items-center">$surcharge2_label<span></span><span>$surcharge2_label: $surcharge2</span></span>',
            '$credit.surcharge3'     => '<span class="flex justify-between items-center">$surcharge3_label<span></span><span>$surcharge3_label: $surcharge3</span></span>',
            '$credit.surcharge4'     => '<span class="flex justify-between items-center">$surcharge4_label<span></span><span>$surcharge4_label: $surcharge4</span></span>',

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
