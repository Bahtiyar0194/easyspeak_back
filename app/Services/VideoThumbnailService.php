<?php
namespace App\Services;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Image;

class VideoThumbnailService
{
    public function generateThumbnails($fileName, $videoFullPath){

        $ffprobe = \FFMpeg\FFProbe::create([
            'ffmpeg.binaries'  => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
            'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
        ]);

        $basename = pathinfo($fileName, PATHINFO_FILENAME);

        try {
            $thumbWidth = 160;
            $thumbHeight = 90;
            $interval = 5;

            $duration = $ffprobe->format($videoFullPath)->get('duration');

            $frames = [];
            $index = 0;

            for ($sec = 0; $sec < $duration; $sec += $interval) {
                $thumbFileName = "{$basename}_{$index}.jpg";
                $thumbFilePath = storage_path('app/public/' . $thumbFileName);

                $mediaThumb = FFMpeg::fromDisk('public')->open($fileName)
                    ->getFrameFromSeconds($sec)
                    ->export()
                    ->toDisk('public')
                    ->save($thumbFileName);

                Image::make($thumbFilePath)
                    ->resize($thumbWidth, $thumbHeight)
                    ->save($thumbFilePath, 85);

                $frames[] = $thumbFileName;
                $index++;
            }

            // --- Создаём sprite ---
            $cols = 10;
            $rows = ceil(count($frames) / $cols);

            $spritePath = storage_path("app/public/{$basename}_sprite.jpg");
            $sprite = Image::canvas($cols * $thumbWidth, $rows * $thumbHeight, '#000000');

            foreach ($frames as $i => $frame) {
                $x = ($i % $cols) * $thumbWidth;
                $y = floor($i / $cols) * $thumbHeight;
                $sprite->insert(storage_path('app/public/' . $frame), 'top-left', $x, $y);
            }

            $sprite->save($spritePath, 85);

            // --- Генерация VTT ---
            $vttPath = storage_path("app/public/{$basename}_thumbs.vtt");
            $vtt = ["WEBVTT\n"];

            foreach ($frames as $i => $frame) {
                $start = gmdate("H:i:s", $i * $interval);
                $end   = gmdate("H:i:s", min(($i + 1) * $interval, $duration));

                $x = ($i % $cols) * $thumbWidth;
                $y = floor($i / $cols) * $thumbHeight;

                $vtt[] = "{$start}.000 --> {$end}.000";
                $vtt[] = "{$basename}_sprite.jpg#xywh={$x},{$y},{$thumbWidth},{$thumbHeight}\n";
            }

            file_put_contents($vttPath, implode("\n", $vtt));

            info("Thumbnails sprite.jpg и VTT успешно созданы.");

            foreach ($frames as $f) {
                @unlink(storage_path('app/public/' . $f));
            }
        } catch (Exception $e) {
            info("Ошибка генерации thumbnails: " . $e->getMessage());
        }
    }
}