<?php

namespace App\Support\KnowledgeBase;

use DOMDocument;
use DOMElement;
use DOMXPath;

class KbContentAnchors
{
    /**
     * Gắn id cho h2/h3 để TOC neo được.
     */
    public static function apply(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div>'.$html.'</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($doc);
        $headings = $xpath->query('//h2|//h3');
        if ($headings === false) {
            return $html;
        }

        $i = 0;
        foreach ($headings as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if (! $node->hasAttribute('id')) {
                $node->setAttribute('id', 'kb-h-'.$i);
            }
            $i++;
        }

        $root = $doc->getElementsByTagName('div')->item(0);
        if (! $root) {
            return $html;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /**
     * @return array<int, array{id:string, text:string, level:int}>
     */
    public static function toc(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        $withIds = self::apply($html);
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$withIds.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($doc);
        $headings = $xpath->query('//h2|//h3');
        if ($headings === false) {
            return [];
        }

        $toc = [];
        foreach ($headings as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $text = trim($node->textContent ?? '');
            if ($text === '') {
                continue;
            }
            $toc[] = [
                'id' => $node->getAttribute('id') ?: 'kb-h-0',
                'text' => $text,
                'level' => strtolower($node->nodeName) === 'h2' ? 2 : 3,
            ];
        }

        return $toc;
    }
}
