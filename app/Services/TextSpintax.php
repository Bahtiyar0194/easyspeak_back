<?php

namespace App\Services;

class TextSpintax
{
    /**
     * Преобразует строку вида "{Привет|Здравствуйте|Добрый день}, ваш заказ №{123|456} готов!"
     */
    public static function parse(string $text): string
    {
        return preg_replace_callback('/\{(((?>[^\{\}]+)|(?R))*)\}/x', function ($matches) {
            $options = explode('|', $matches[1]);
            return $options[array_rand($options)];
        }, $text);
    }
}