<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

class PdfDesigner
{
    const BOLD = 'bold';
    const BUSINESS = 'business';
    const CLEAN = 'clean';
    const CREATIVE = 'creative';
    const ELEGANT = 'elegant';
    const HIPSTER = 'hipster';
    const MODERN = 'modern';
    const PLAIN = 'plain';
    const PLAYFUL = 'playful';
    const CUSTOM = 'custom';
    const CALM = 'calm';
    
    const DELIVERY_NOTE = 'delivery_note';
    const STATEMENT = 'statement';
    const PURCHASE_ORDER = 'purchase_order';

    public string $template;

    public function __construct(public PdfService $service)
    {
    }

    public function build() :self
    {
        /*If the design is custom*/
        if ($this->service->config->design->is_custom) {
            $this->template = $this->composeFromPartials(json_decode(json_encode($this->service->config->design->design), true));
        } else {
            $this->template = file_get_contents(config('ninja.designs.base_path') . strtolower($this->service->config->design->name) . '.html');
        }

        return $this;
    }

    /**
     * If the user has implemented a custom design, then we need to rebuild the design at this point
     */

    /**
     * Returns the custom HTML design as
     * a string
     *
     * @param  array
     * @return string
     *
     */
    private function composeFromPartials(array $partials) :string
    {
        $html = '';

        $html .= $partials['includes'];
        $html .= $partials['header'];
        $html .= $partials['body'];
        $html .= $partials['footer'];

        return $html;
    }
}
