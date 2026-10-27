<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Membersihkan HTML dari luar (isi email Interdotz, editor Quill) dengan daftar izin:
 * hanya tag format teks dan tautan http/https/mailto yang tersisa. Atribut lain, skrip,
 * gaya, dan elemen tersemat dibuang; tag lain dilepas tapi teksnya tetap.
 */
final class SafeHtml
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'a',
    ];

    /** Dibuang beserta seluruh isinya. */
    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math',
        'template', 'noscript', 'textarea', 'select', 'button', 'form', 'input', 'link', 'meta',
        'base', 'title', 'head', 'img', 'picture', 'source', 'audio', 'video', 'canvas',
    ];

    private const VOID_TAGS = ['br', 'hr'];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        return $body ? self::children($body) : '';
    }

    private static function children(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= self::node($child);
        }

        return $html;
    }

    private static function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return self::escape($node->textContent);
        }
        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DROPPED_TAGS, true)) {
            return '';
        }
        if (in_array($tag, self::VOID_TAGS, true)) {
            return "<{$tag}>";
        }

        $inner = self::children($node);
        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            return $inner;
        }

        if ($tag === 'a') {
            $href = self::safeUrl($node->getAttribute('href'));

            return $href === null
                ? $inner
                : '<a href="' . self::escape($href) . '" target="_blank" rel="noopener noreferrer nofollow">' . $inner . '</a>';
        }

        return "<{$tag}>{$inner}</{$tag}>";
    }

    /** Hanya http, https, dan mailto; spasi/karakter kontrol tidak bisa menyamarkan "javascript:". */
    private static function safeUrl(string $url): ?string
    {
        $url = trim($url);
        $compact = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        return preg_match('#^(https?:|mailto:)#i', $compact) === 1 ? $url : null;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
