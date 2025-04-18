<?php

if (!function_exists('normalizeQuotes')) {
    function normalizeQuotes(string $text): string
    {
        $text = str_replace(
            ['’', '‘', '‛', '“', '”', '„', '‟', '«', '»', '—', '–', "\u{00A0}", '&nbsp;', ' '],
            ["'", "'", "'", '"', '"', '"', '"', '"', '"', '-', '-', ' ', ' ', ' '],
            $text
        );

        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
?>