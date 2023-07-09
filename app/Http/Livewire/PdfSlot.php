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

namespace App\Http\Livewire;

use App\Libraries\MultiDB;
use Livewire\Component;

class PdfSlot extends Component
{
    public $invitation;

    public $db;

    public $entity;

    public $pdf;

    public $url;
    
    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    public function render()
    {
        return render('components.livewire.pdf-slot', [
            'invitation' => $this->invitation,
            'entity' => $this->entity,
        ]);
    }

    public function getPdf()
    {
        
        $this->pdf =  $this->entity->fullscreenPdfViewer($this->invitation);

    }
}
