<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Sanitizador HTML basado en DOMDocument con lista blanca estricta.
 *
 * Evita XSS al limpiar contenido enviado por usuarios (replies, comentarios)
 * que despues se renderiza con v-html. No depende de paquetes externos.
 *
 * Reglas:
 *  - Solo se conservan los tags listados en TAGS_PERMITIDOS.
 *  - Los demas tags se reemplazan por su contenido textual (los hijos suben).
 *  - Atributos: por defecto se eliminan todos. Solo <a> conserva href si el
 *    esquema es http(s) o mailto. Cualquier href con javascript:, data:,
 *    vbscript:, etc. queda descartado.
 *  - Los nodos <script>, <style>, <iframe>, <object>, <embed>, <link>, <meta>
 *    se eliminan junto con su contenido (drop completo).
 */
class HtmlSanitizer
{
    private const TAGS_PERMITIDOS = [
        'b', 'i', 'p', 'a', 'ul', 'ol', 'li', 'br', 'strong', 'em',
    ];

    private const TAGS_ELIMINAR_CON_CONTENIDO = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta',
        'form', 'input', 'button', 'textarea', 'select', 'option',
    ];

    private const ESQUEMAS_HREF_PERMITIDOS = ['http', 'https', 'mailto'];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        // Codifica caracteres no-ASCII como entidades numericas para evitar
        // el problema de DOMDocument::loadHTML que asume latin-1 sin meta.
        $htmlEntities = mb_encode_numericentity(
            $html,
            [0x80, 0x10FFFF, 0, 0x1FFFFF],
            'UTF-8'
        );
        $wrapped = '<sanitizer-root>' . $htmlEntities . '</sanitizer-root>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $contenedor = $doc->getElementsByTagName('sanitizer-root')->item(0);
        if (!$contenedor) {
            return '';
        }

        $xpath = new DOMXPath($doc);
        foreach (self::TAGS_ELIMINAR_CON_CONTENIDO as $tag) {
            foreach (iterator_to_array($xpath->query(".//{$tag}", $contenedor)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Iterar solo descendientes del contenedor (no el contenedor mismo).
        foreach (iterator_to_array($contenedor->getElementsByTagName('*')) as $element) {
            self::procesarElemento($element);
        }

        $resultado = '';
        foreach ($contenedor->childNodes as $hijo) {
            $resultado .= $doc->saveHTML($hijo);
        }

        return trim($resultado);
    }

    private static function procesarElemento(DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);

        if (!in_array($tag, self::TAGS_PERMITIDOS, true)) {
            self::desenvolver($element);
            return;
        }

        foreach (iterator_to_array($element->attributes) as $attr) {
            $nombre = strtolower($attr->nodeName);

            if ($tag === 'a' && $nombre === 'href' && self::esHrefSeguro($attr->nodeValue)) {
                continue;
            }

            $element->removeAttribute($attr->nodeName);
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
            $element->setAttribute('target', '_blank');
        }
    }

    private static function esHrefSeguro(?string $href): bool
    {
        if ($href === null || trim($href) === '') {
            return false;
        }

        $href = trim($href);
        $esquema = strtolower(parse_url($href, PHP_URL_SCHEME) ?? '');

        if ($esquema === '') {
            return str_starts_with($href, '/') || str_starts_with($href, '#');
        }

        return in_array($esquema, self::ESQUEMAS_HREF_PERMITIDOS, true);
    }

    private static function desenvolver(DOMElement $element): void
    {
        $padre = $element->parentNode;
        if (!$padre) {
            return;
        }

        while ($element->firstChild) {
            $padre->insertBefore($element->firstChild, $element);
        }
        $padre->removeChild($element);
    }
}
