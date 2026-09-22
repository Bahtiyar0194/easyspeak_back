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
    function humanDate($date, $lang_tag)
    {
        if(!isset($lang_tag)){
            Carbon::setLocale(app()->getLocale());
        }

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
    function getNextDate(Carbon $current, array $selectedDays): Carbon
    {
        return collect($selectedDays)
            ->map(function ($day) use ($current) {
                // Если id = 7 (Воскресенье по ISO), превращаем его в 0 для Carbon
                $dayOfWeek = (int) $day['id'] % 7;

                // Carbon::next(0..6) работает без ошибок
                $nextDate = $current->copy()->next($dayOfWeek);

                // Если задано персональное время для этого дня — устанавливаем его
                if (!empty($day['start_time'])) {
                    [$hour, $minute] = explode(':', $day['start_time']);
                    $nextDate->setTime((int)$hour, (int)$minute, 0);
                }

                return $nextDate;
            })
            ->sort()
            ->first();
    }
}
?>