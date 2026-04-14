<?php

namespace SalmutterNet\JoomlaHelpers;

final class Text
{
    /**
     * Remove a UTF-8 BOM (Byte Order Mark) from the beginning of a string.
     */
    public static function trimBom(string $string): string
    {
        $bom = "\xEF\xBB\xBF";

        if (str_starts_with($string, $bom)) {
            return substr($string, 3);
        }

        return $string;
    }

    /**
     * Truncate plain text at a word/space boundary.
     */
    public static function truncateAtSpace(string $string, int $maxLength, string $suffix = '…'): string
    {
        $string = strip_tags($string);

        if (mb_strlen($string) <= $maxLength) {
            return $string;
        }

        $truncated = mb_substr($string, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > $maxLength * 0.6) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated . $suffix;
    }

    /**
     * Extract the first <p>...</p> block from an HTML string.
     */
    public static function firstParagraph(string $html): string
    {
        $pos = stripos($html, '</p>');

        if ($pos === false) {
            return $html;
        }

        return substr($html, 0, $pos + 4);
    }

    /**
     * Truncate HTML to a maximum visible character length while preserving tag structure.
     *
     * Unlike the old snipHtml(), this returns a string instead of printing directly.
     */
    public static function truncateHtml(string $html, int $maxLength, bool $isUtf8 = true, bool $firstParagraphOnly = false): string
    {
        // Strip images
        $html = preg_replace('/<img[^>]+>/i', '', $html) ?? $html;

        if ($firstParagraphOnly) {
            $html = self::firstParagraph($html);
        }

        $printedLength = 0;
        $position = 0;
        $tags = [];
        $output = '';

        $re = $isUtf8
            ? '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;|[\x80-\xFF][\x80-\xBF]*}'
            : '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;}';

        while ($printedLength < $maxLength && preg_match($re, $html, $match, PREG_OFFSET_CAPTURE, $position)) {
            [$tag, $tagPosition] = $match[0];

            // Text before this tag
            $text = substr($html, $position, $tagPosition - $position);
            if ($printedLength + strlen($text) > $maxLength) {
                $output .= substr($text, 0, $maxLength - $printedLength);
                $printedLength = $maxLength;
                break;
            }

            $output .= $text;
            $printedLength += strlen($text);

            if ($printedLength >= $maxLength) {
                break;
            }

            if ($tag[0] === '&' || ord($tag) >= 0x80) {
                // Entity or multibyte sequence
                $output .= $tag;
                $printedLength++;
            } else {
                // HTML tag
                $tagName = $match[1][0] ?? '';

                if ($tag[1] === '/') {
                    // Closing tag
                    array_pop($tags);
                    $output .= $tag;
                } elseif ($tag[strlen($tag) - 2] === '/') {
                    // Self-closing tag
                    $output .= $tag;
                } else {
                    // Opening tag
                    $output .= $tag;
                    $tags[] = $tagName;
                }
            }

            $position = $tagPosition + strlen($tag);
        }

        // Remaining text
        if ($printedLength < $maxLength && $position < strlen($html)) {
            $output .= substr($html, $position, $maxLength - $printedLength);
        }

        if ($printedLength >= $maxLength) {
            $output .= '…';
        }

        // Close any open tags
        while ($tags !== []) {
            $output .= '</' . array_pop($tags) . '>';
        }

        return $output;
    }
}
