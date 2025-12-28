<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

class Markdown
{
    public static function parse(string $markdown): string
    {
        $converter = new \League\CommonMark\CommonMarkConverter([
            'allow_unsafe_links' => false,
            // 'html_input' => 'allow',
        ]);

        return $converter->convert($markdown);

    }
}
