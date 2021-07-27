<?php

namespace App\Utils;

class MarkdownParser
{
    public static function parse(string $original): string
    {
        $lines = \preg_split("/\r\n|\n|\r/", $original);
        $markdowns = [];

        foreach ($lines as $line) {
            if (\Illuminate\Support\Str::startsWith(\trim($line), ['#'])) {
                $markdowns[] = \trim($line);
            }
        }

        foreach ($markdowns as $key => $markdown) {
            $parts = \explode('#', $markdown);

            $tag = self::getHtmlTag($markdown);

            $wrap = \sprintf('<%s>%s<%s>', $tag, \implode('', $parts), $tag);

            $original = \str_replace($markdown, $wrap, $original);
        }

        return $original;
    }

    protected static function getHtmlTag(string $markdown): ?string
    {
        $count = 0;

        $parts = \explode('#', $markdown);

        foreach ($parts as $key => $value) {
            if (empty($value)) {
                $count++;
            }
        }

        return 'h' . $count;
    }
}
