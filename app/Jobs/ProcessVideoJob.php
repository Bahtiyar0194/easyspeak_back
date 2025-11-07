<?php

namespace App\Jobs;

use App\Models\MediaFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use FFMpeg\Format\Video\X264;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Storage;
use Exception;
use Log;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileName;

    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
    }

    public function handle(): void
    {
        try {
            $videoPath = 'public/' . $this->fileName;

            // --- 1. Получаем исходное разрешение ---
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffmpeg.binaries'  => env('FFMPEG_PATH', 'C:\ffmpeg\bin\ffmpeg.exe'),
                'ffprobe.binaries' => env('FFPROBE_PATH', 'C:\ffmpeg\bin\ffprobe.exe'),
            ]);

            $videoFullPath = storage_path('app/' . $videoPath);
            $stream = $ffprobe->streams($videoFullPath)->videos()->first();

            $width = $stream->get('width');
            $height = $stream->get('height');
            info("Исходное разрешение видео: {$width}x{$height}");

            // --- 2. Разрешения ---
            $resolutions = [
                ['width' => 640,  'height' => 360,  'bitrate' => 500],
                ['width' => 854,  'height' => 480,  'bitrate' => 1000],
                // ['width' => 1280, 'height' => 720,  'bitrate' => 2500],
                // ['width' => 1920, 'height' => 1080, 'bitrate' => 5000],
            ];

            $allowedResolutions = array_filter($resolutions, function ($r) use ($height) {
                return $r['height'] <= $height;
            });

            info('Выбранные разрешения: ' . json_encode($allowedResolutions));

            $findFile = MediaFile::where('target', $this->fileName)->first();

            if (!$findFile) {
                info("Файл {$this->fileName} не найден в БД.");
                return;
            }

            if (empty($allowedResolutions)) {
                $findFile->processing = 0;
                $findFile->save();
                info('Видео слишком маленькое, HLS не требуется.');
                return;
            }

            $findFile->processing = 1;
            $findFile->save();

            // --- 3. Конвертация ---
            $media = FFMpeg::fromDisk('local')->open($videoPath);

            $export = $media->exportForHLS()->onProgress(function ($p) {
                info("Обработка: {$p}%");
            });

            foreach ($allowedResolutions as $res) {
                $export->addFormat(
                    (new X264('aac', 'libx264'))
                        ->setKiloBitrate($res['bitrate'])
                        ->setAudioKiloBitrate(128),
                    function ($media) use ($res) {
                        $media->addFilter("scale={$res['width']}:{$res['height']}");
                    }
                );
            }

            $outputName = pathinfo($this->fileName, PATHINFO_FILENAME) . ".m3u8";
            $export->toDisk('public')->save($outputName);

            // --- 4. Удаляем исходное видео ---

            // if (Storage::disk('public')->exists($this->fileName)) {
            //     Storage::disk('public')->delete($this->fileName);
            //     info("Исходный файл {$this->fileName} успешно удалён после HLS-конвертации.");
            // } else {
            //     info("Исходный файл {$this->fileName} не найден для удаления.");
            // }

            // --- 5. Обновляем запись в БД ---
            $findFile->target = $outputName;
            $findFile->processing = 0;
            $findFile->save();

            info("Видео успешно обработано: {$outputName}");
        } catch (Exception $e) {
            info('Ошибка при обработке видео: ' . $e->getMessage());
        }
    }
}