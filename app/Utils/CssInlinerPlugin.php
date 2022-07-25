<?php

namespace App\Utils;

use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class CssInlinerPlugin implements \Swift_Events_SendListener
{
    /**
     * @var CssToInlineStyles
     */
    protected $converter;

    /**
     * @var string[]
     */
    protected $cssCache;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param array $options options defined in the configuration file.
     */
    public function __construct(array $options)
    {
        $this->converter = new CssToInlineStyles();
        $this->options = $options;
    }

    /**
     * @param \Swift_Events_SendEvent $evt
     */
    public function beforeSendPerformed(\Swift_Events_SendEvent $evt)
    {
        $message = $evt->getMessage();

        if ($message->getContentType() === 'text/html'
            || ($message->getContentType() === 'multipart/alternative' && $message->getBody())
            || ($message->getContentType() === 'multipart/mixed' && $message->getBody())
        ) {
            [$body, $cssResources] = $this->messageSieve($message->getBody());
            $css = $this->concatCss($cssResources);
            $message->setBody($this->converter->convert($body, $css));
        }

        foreach ($message->getChildren() as $part) {
            if (strpos($part->getContentType(), 'text/html') === 0) {
                [$body, $cssResources] = $this->messageSieve($part->getBody());
                $css = $this->concatCss($cssResources);
                $part->setBody($this->converter->convert($body, $css));
            }
        }
    }

    /**
     * Do nothing
     *
     * @param \Swift_Events_SendEvent $evt
     */
    public function sendPerformed(\Swift_Events_SendEvent $evt)
    {
        // Do Nothing
    }

    protected function concatCss(array $cssResources): string
    {
        $output = '';
        foreach ($cssResources as $cssResource) {
            $output .= $this->fetchCss($cssResource);
        }

        return $output;
    }

    protected function fetchCss(string $filename): string
    {
        if (isset($this->cssCache[$filename])) {
            return $this->cssCache[$filename];
        }

        $fixedFilename = $filename;
        // Fix relative protocols on hrefs. Assume https.
        if (substr($filename, 0, 2) === '//') {
            $fixedFilename = 'https:'.$filename;
        }

        $content = file_get_contents($fixedFilename);
        if (! $content) {
            return '';
        }

        $this->cssCache[$filename] = $content;

        return $content;
    }

    protected function messageSieve(string $message): array
    {
        $cssResources = [];

        // Initialize with config defaults, if any
        if (isset($this->options['css-files'])) {
            $cssResources = $this->options['css-files'];
        }

        $dom = new \DOMDocument();
        // set error level
        $internalErrors = libxml_use_internal_errors(true);

        $dom->loadHTML($message);

        // Restore error level
        libxml_use_internal_errors($internalErrors);
        $link_tags = $dom->getElementsByTagName('link');

        /** @var \DOMElement $link */
        foreach ($link_tags as $link) {
            if ($link->getAttribute('rel') === 'stylesheet') {
                array_push($cssResources, $link->getAttribute('href'));
            }
        }

        $link_tags = $dom->getElementsByTagName('link');
        for ($i = $link_tags->length; --$i >= 0;) {
            $link = $link_tags->item($i);
            if ($link->getAttribute('rel') === 'stylesheet') {
                $link->parentNode->removeChild($link);
            }
        }

        if (count($cssResources)) {
            return [$dom->saveHTML(), $cssResources];
        }

        return [$message, []];
    }
}
