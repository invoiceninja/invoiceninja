<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\Flow2;

use Livewire\Component;
use App\Libraries\MultiDB;
use App\Utils\Traits\WithSecureContext;

class DocuNinja extends Component
{
    use WithSecureContext;

    // Properties to store DocuNinja internal state
    public $docuNinjaCredentials = [];
    public $docuNinjaFormData = [];
    public $docuNinjaSignatureData = [];
    public $docuNinjaSigningStatus = 'unknown';
    public $docuNinjaInternalState = [];

    public $_key;

    private ?string $document_id = null;
    private ?string $document_invitation_id = null;
    private ?string $sig = null;
    private ?string $company_key = null;

    public function mount()
    {
        MultiDB::setDb($this->getContext($this->_key)['db']);

        $signable = $this->getContext($this->_key)['docuninja_signable'] ?? null;

        if ($signable) {
            $this->document_id = $signable['document_id'];
            $this->document_invitation_id = $signable['document_invitation_id'];
            $this->sig = $signable['sig'];
            $this->company_key = $signable['company_key'];
        } else {
            $this->dispatch('docuninja-signature-captured');
        }
    }

    public function placeholder()
    {
        return <<<'HTML'
        <div>
            <!-- Loading spinner... -->
            <svg>...</svg>
        </div>
        HTML;
    }
    
    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return render('flow2.docu-ninja', [
            'token' => '',
            'document' => $this->document_id,
            'invitation' => $this->document_invitation_id,
            'sig' => $this->sig,
            'company_key' => $this->company_key,
            '_key' => $this->_key,
        ]);
    }

    /**
     * Handle DocuNinja state changes from JavaScript
     * This method receives the internal variables and state from the DocuNinja component
     */
    public function onDocuNinjaStateChange($data)
    {
        // Store the received data in component properties
        $this->docuNinjaCredentials = $data['credentials'] ?? [];
        $this->docuNinjaFormData = $data['formData'] ?? [];
        $this->docuNinjaSignatureData = $data['signatureData'] ?? [];
        $this->docuNinjaSigningStatus = $data['signingStatus'] ?? 'unknown';
        $this->docuNinjaInternalState = $data['fullState'] ?? [];
        
        // Log the received data for debugging
        \Log::info('DocuNinja state change received', $data);
        
        // You can now use these variables in your component
        $this->dispatch('docuNinjaStateUpdated', [
            'credentials' => $this->docuNinjaCredentials,
            'formData' => $this->docuNinjaFormData,
            'signatureData' => $this->docuNinjaSignatureData,
            'signingStatus' => $this->docuNinjaSigningStatus,
            'internalState' => $this->docuNinjaInternalState
        ]);
    }
    
    /**
     * Get the current DocuNinja credentials
     */
    public function getDocuNinjaCredentials()
    {
        return $this->docuNinjaCredentials;
    }
    
    /**
     * Get the current DocuNinja form data
     */
    public function getDocuNinjaFormData()
    {
        return $this->docuNinjaFormData;
    }
    
    /**
     * Get the current DocuNinja signature data
     */
    public function getDocuNinjaSignatureData()
    {
        return $this->docuNinjaSignatureData;
    }
    
    /**
     * Get the current DocuNinja signing status
     */
    public function getDocuNinjaSigningStatus()
    {
        return $this->docuNinjaSigningStatus;
    }
    
    /**
     * Get the complete DocuNinja internal state
     */
    public function getDocuNinjaInternalState()
    {
        return $this->docuNinjaInternalState;
    }
    
    /**
     * Check if DocuNinja has specific credentials
     */
    public function hasDocuNinjaCredentials()
    {
        return !empty($this->docuNinjaCredentials);
    }
    
    /**
     * Check if DocuNinja has form data
     */
    public function hasDocuNinjaFormData()
    {
        return !empty($this->docuNinjaFormData);
    }
    
    /**
     * Check if DocuNinja has signature data
     */
    public function hasDocuNinjaSignatureData()
    {
        return !empty($this->docuNinjaSignatureData);
    }
    
    /**
     * Get a specific value from DocuNinja form data
     */
    public function getDocuNinjaFormValue($key, $default = null)
    {
        return $this->docuNinjaFormData[$key] ?? $default;
    }
    
    /**
     * Get a specific value from DocuNinja credentials
     */
    public function getDocuNinjaCredential($key, $default = null)
    {
        return $this->docuNinjaCredentials[$key] ?? $default;
    }
    
    /**
     * Check if DocuNinja is in a specific signing status
     */
    public function isDocuNinjaStatus($status)
    {
        return $this->docuNinjaSigningStatus === $status;
    }
    
    /**
     * Check if DocuNinja is ready for signing
     */
    public function isDocuNinjaReady()
    {
        return $this->isDocuNinjaStatus('ready') || $this->isDocuNinjaStatus('initialized');
    }
    
    /**
     * Check if DocuNinja is currently signing
     */
    public function isDocuNinjaSigning()
    {
        return $this->isDocuNinjaStatus('signing') || $this->isDocuNinjaStatus('in_progress');
    }
    
    /**
     * Check if DocuNinja has completed signing
     */
    public function isDocuNinjaCompleted()
    {
        return $this->isDocuNinjaStatus('completed') || $this->isDocuNinjaStatus('finished');
    }
    
    /**
     * Refresh the DocuNinja state (useful for debugging)
     */
    public function refreshDocuNinjaState()
    {
        // This method can be called to manually refresh the state
        // The actual state will be updated via JavaScript events
        $this->dispatch('docuNinjaStateRefreshed', [
            'timestamp' => now(),
            'currentState' => [
                'credentials' => $this->docuNinjaCredentials,
                'formData' => $this->docuNinjaFormData,
                'signatureData' => $this->docuNinjaSignatureData,
                'signingStatus' => $this->docuNinjaSigningStatus,
                'internalState' => $this->docuNinjaInternalState
            ]
        ]);
        
        // Log the refresh action
        \Log::info('DocuNinja state refresh requested');
    }
    
    /**
     * Clear the DocuNinja state
     */
    public function clearDocuNinjaState()
    {
        $this->docuNinjaCredentials = [];
        $this->docuNinjaFormData = [];
        $this->docuNinjaSignatureData = [];
        $this->docuNinjaSigningStatus = 'unknown';
        $this->docuNinjaInternalState = [];
        
        // Dispatch event to notify that state was cleared
        $this->dispatch('docuNinjaStateCleared');
        
        // Log the clear action
        \Log::info('DocuNinja state cleared');
    }

    public function exception($e, $stopPropagation)
    {
        app('sentry')->captureException($e);
        nlog($e->getMessage());
        $stopPropagation();
    }
}



