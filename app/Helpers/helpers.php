<?php
use Carbon\Carbon;

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

if (! function_exists('humanDate')) {
    function humanDate($date)
    {
        Carbon::setLocale(app()->getLocale());
        $dt = Carbon::parse($date);

        if ($dt->isToday()) {
            return trans('app.today') . ', ' . $dt->format('H:i');
        }

        if ($dt->isTomorrow()) {
            return trans('app.tomorrow') . ', ' . $dt->format('H:i');
        }

        if ($dt->isYesterday()) {
            return trans('app.yesterday') . ', ' . $dt->format('H:i');
        }

        return $dt->translatedFormat('j F, H:i');
    }
}

if (! function_exists('getNextDate')) {
    function getNextDate(Carbon $current, array $days): Carbon
    {
        $hour = $current->hour;
        $minute = $current->minute;
        $second = $current->second;

        return collect($days)
            ->map(function ($day) use ($current, $hour, $minute, $second) {
                return $current->copy()
                    ->next($day)
                    ->setTime($hour, $minute, $second);
            })
            ->sort()
            ->first();
    }
}
?>