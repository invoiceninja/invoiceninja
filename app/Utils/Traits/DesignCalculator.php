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

namespace App\Utils\Traits;

trait DesignCalculator
{
    private function resolveCompanyLogoSize()
    {
        $design_map = [
            "VolejRejNm" => "65%", // "Plain",
            "Wpmbk5ezJn" => "65%", //"Clean",
            "Opnel5aKBz" => "65%", //"Bold",
            "wMvbmOeYAl" => "55%", //Modern",
            "4openRe7Az" => "65%", //"Business",
            "WJxbojagwO" => "65%", //"Creative",
            "k8mep2bMyJ" => "55%", //"Elegant",
            "l4zbq2dprO" => "65%", //"Hipster",
            "yMYerEdOBQ" => "65%", //"Playful",
            "gl9avmeG1v" => "65%", //"Tech",
            "7LDdwRb1YK" => "65%", //"Calm",
            "APdRoy0eGy" => "65%", //"Calm-DB2",
            "y1aK83rbQG" => "65%", //"Calm-DB1",
        ];

        $design_int_map = [
            "1" => "65%", // "Plain",
            "2" => "65%", //"Clean",
            "3" => "65%", //"Bold",
            "4" => "55%", //Modern",
            "5" => "65%", //"Business",
            "6" => "65%", //"Creative",
            "7" => "55%", //"Elegant",
            "8" => "65%", //"Hipster",
            "9" => "65%", //"Playful",
            "10" => "65%", //"Tech",
            "11" => "65%", //"Calm",
            "6972" => "65%", //"C-DB2"
            "11221" => "65%", //"C-DB1"
        ];

        if (isset($this->settings->company_logo_size) && strlen($this->settings->company_logo_size) > 1) {
            return $this->settings->company_logo_size;
        }

        if ($this->entity->design_id && array_key_exists($this->entity->design_id, $design_int_map)) {
            return $design_int_map[$this->entity->design_id];
        }

        $default_design_id = $this->entity_string."_design_id";

        if ($default_design_id == 'recurring_invoice_design_id') {
            $default_design_id = 'invoice_design_id';
        }

        $design_id = $this->settings->{$default_design_id};

        if (array_key_exists($design_id, $design_map)) {
            return $design_map[$design_id];
        }

        return '65%';
    }
}
