<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits\Pdf;

use App\Exceptions\InternalPDFFailure;
use Beganovich\Snappdf\Snappdf;

trait PdfMaker
{
    /**
     * Returns a PDF stream.
     *
     * @param  string|null $header Header to be included in PDF
     * @param  string|null $footer Footer to be included in PDF
     * @param  string $html   The HTML object to be converted into PDF
     *
     * @return string        The PDF string
     */
    public function makePdf($header, $footer, $html)
    {
        $pdf = new Snappdf();

        $chrome_flags = [
            '--headless',
            '--no-sandbox',
            '--disable-gpu',
            '--no-margins',
            '--hide-scrollbars',
            '--no-first-run',
            '--no-default-browser-check',

            // PDF-specific settings
            '--print-to-pdf-no-header',
            '--no-pdf-header-footer',

            // Security settings
            '--block-insecure-private-network-requests',
            '--block-port=22,25,465,587',
            '--disable-usb',
            '--disable-webrtc',
            '--block-new-web-contents',
            '--deny-permission-prompts',
            '--ignore-certificate-errors',

            // Performance & resource settings
            '--disable-dev-shm-usage',
            '--run-all-compositor-stages-before-draw',
            '--disable-renderer-backgrounding',
            '--disable-background-timer-throttling',
            // '--disable-background-networking',
            '--disable-domain-reliability',
            '--disable-ipc-flooding-protection',

            // Feature disabling
            '--disable-translate',
            '--disable-extensions',
            '--disable-sync',
            '--disable-default-apps',
            '--disable-plugins',
            '--disable-notifications',
            '--disable-device-discovery-notifications',
            '--disable-reading-from-canvas',
            '--safebrowsing-disable-auto-update',
            '--disable-features=SharedArrayBuffer,OutOfBlinkCors,PerformanceManager,InterestCohort',

            // '--wait-for-network-idle',
            '--font-render-hinting=medium',
            '--enable-font-antialiasing',
            // important for background-images
            '--virtual-time-budget=10000',
        ];

        // Add Chromium arguments - each flag should be added separately
        $pdf->clearChromiumArguments();
        foreach ($chrome_flags as $flag) {
            $pdf->addChromiumArguments($flag);
        }

        if (config('ninja.snappdf_chromium_path')) {
            $pdf->setChromiumPath(config('ninja.snappdf_chromium_path'));
        }

        $html = str_ireplace(['file:/', 'iframe', '<embed', '&lt;embed', '&lt;object', '<object', '127.0.0.1', 'localhost', '<?xml encoding="UTF-8">', '/etc/'], '', $html);
        // nlog($html);
        
        try {
            $generated = $pdf
                            ->setHtml($html)
                            ->generate();

            if ($generated && strlen($generated) > 0) {
                // Verify it's a valid PDF (starts with %PDF)
                if (substr($generated, 0, 4) === '%PDF') {
                    return $generated;
                } else {
                    nlog('PDF generation returned invalid content. First 100 bytes: ' . substr($generated, 0, 100));
                    throw new InternalPDFFailure('PDF generation returned invalid content (not a valid PDF file)');
                }
            }
        } catch (\Exception $e) {
            nlog('PDF generation error: ' . $e->getMessage());
            if ($e instanceof \Symfony\Component\Process\Exception\ProcessFailedException) {
                $process = $e->getProcess();
                nlog('Process output: ' . $process->getOutput());
                nlog('Process error output: ' . $process->getErrorOutput());
                throw new InternalPDFFailure('There was an issue generating the PDF locally: ' . $process->getErrorOutput() ?: $e->getMessage());
            }
            throw new InternalPDFFailure('There was an issue generating the PDF locally: ' . $e->getMessage());
        }

        throw new InternalPDFFailure('There was an issue generating the PDF locally: PDF generation returned empty result');
    }
}
