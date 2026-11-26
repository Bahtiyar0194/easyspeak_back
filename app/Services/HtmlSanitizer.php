<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Очистка HTML результатов заданий по белому списку.
 *
 * Разметку ответов (right_answer, user_answer, comment) строит фронтенд, а выводится она
 * через v-html, поэтому всё, что приходит с клиента, нужно чистить: иначе ученик может
 * сохранить скрипт, который выполнится у ментора.
 *
 * Разрешены только теги, которые фронтенд реально использует в ответах, и атрибуты
 * class (только безопасные символы) и aria-hidden. Опасные теги удаляются вместе
 * с содержимым, остальные неизвестные — «разворачиваются» (остаётся их текст).
 */
class HtmlSanitizer
{
    const ALLOWED_TAGS = ['div', 'span', 'p', 'b', 'strong', 'i', 'em', 'u', 's', 'small', 'sub', 'sup', 'br', 'ul', 'ol', 'li'];

    const ALLOWED_ATTRIBUTES = ['class', 'aria-hidden'];

    /** Удаляются целиком, вместе с содержимым */
    const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math',
        'template', 'noscript', 'textarea', 'select', 'option', 'form', 'input', 'button', 'link', 'meta',
        'base', 'title', 'head', 'img', 'video', 'audio', 'source', 'track', 'picture', 'canvas', 'map', 'area',
    ];

    public function clean(?string $html): ?string
    {
        return $this->process($html, true);
    }

    /**
     * Разобрать и собрать HTML обратно без фильтрации. Нужен, чтобы отличить
     * реальные изменения от косметических (кавычки, сущности) при очистке старых данных.
     */
    public function normalize(?string $html): ?string
    {
        return $this->process($html, false);
    }

    protected function process(?string $html, bool $filter): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        // Без тегов и сущностей разбирать нечего — возвращаем как есть
        if (strpbrk($html, '<>&') === false) {
            return $html;
        }

        // «<», за которым не идёт тег («a < b», «<3»), — это текст; иначе парсер его выбросит
        $html = preg_replace('/<(?![a-zA-Z\/!?])/', '&lt;', $html);

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root') ?? $doc->getElementsByTagName('div')->item(0);

        if (!$root) {
            return e(strip_tags($html));
        }

        if ($filter) {
            $this->cleanChildren($root);
        } else {
            // Комментарии (в т.ч. служебные <!--v-if--> от Vue) не несут смысла — убираем и здесь,
            // чтобы их удаление очисткой не считалось изменением
            foreach (iterator_to_array((new \DOMXPath($doc))->query('.//comment()', $root)) as $comment) {
                $comment->parentNode->removeChild($comment);
            }
        }

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    protected function cleanChildren(DOMNode $node): void
    {
        // Копия списка: узлы удаляются и заменяются по ходу
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                // Комментарии, CDATA, инструкции обработки
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            $this->cleanChildren($child);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Неизвестный тег: оставляем его (уже очищенное) содержимое
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);
                continue;
            }

            $this->cleanAttributes($child);
        }
    }

    protected function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (!in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            // Классы Tailwind: буквы, цифры и . : - _ / [ ] % # пробелы
            if ($name === 'class' && !preg_match('/^[\w\s.:\-\/\[\]%#]*$/u', $attribute->value)) {
                $element->removeAttribute($attribute->name);
            }

            if ($name === 'aria-hidden' && !in_array($attribute->value, ['true', 'false'], true)) {
                $element->removeAttribute($attribute->name);
            }
        }
    }
}
