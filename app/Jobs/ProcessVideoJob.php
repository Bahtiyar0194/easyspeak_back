<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Models\VideoResolutionType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use Storage;
use Exception;

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
        $basename = pathinfo($this->fileName, PATHINFO_FILENAME);
        $videoPath = 'public/' . $this->fileName;

        try {
            // --- Получаем исходное разрешение ---
            $ffprobe = \FFMpeg\FFProbe::create([
                'ffmpeg.binaries'  => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
            ]);

            $videoFullPath = storage_path('app/' . $videoPath);
            $stream = $ffprobe->streams($videoFullPath)->videos()->first();

            $width = $stream->get('width');
            $height = $stream->get('height');

            info("Исходное видео: {$width}x{$height}");

            // --- Получаем список разрешений из БД ---
            $resolutions = \App\Models\VideoResolutionType::where('is_active', 1)->get();

            // --- Фильтруем только те, что не превышают исходное разрешение ---
            $allowed = $resolutions->filter(function ($r) use ($height) {
                return $r->height <= $height;
            })->values();

            if ($allowed->isEmpty()) {
                info('Видео слишком маленькое — HLS не требуется.');
                return;
            }

            $findFile = MediaFile::where('target', $this->fileName)->first();
            if (!$findFile) {
                info("Файл {$this->fileName} не найден в БД.");
                return;
            }

            $findFile->processing = 1;
            $findFile->save();

            $variants = [];

            // --- Обрабатываем каждое качество поочередно ---
            foreach ($allowed as $r) {
                $label = "{$r->height}p";
                $playlistName = "{$basename}_{$label}";
                $playlistNameFile = "{$playlistName}.m3u8";
                $playlistNameFinal = "{$playlistName}_0_{$r->bitrate}.m3u8";

                info("Начало конвертации {$label}...");

                $media = FFMpeg::fromDisk('local')->open($videoPath);

                $format = (new X264('aac', 'libx264'))
                    ->setKiloBitrate($r->bitrate)
                    ->setAudioKiloBitrate(128)
                    ->setAdditionalParameters(['-threads', '1', '-preset', 'veryfast']);

                // Экспорт HLS для одного качества
                $media->exportForHLS()
                     ->onProgress(function($p) use ($label) {
                        info("{$label}: {$p}%");
                    })
                    ->addFormat($format, function($v) use ($r) {
                        $v->addFilter("scale={$r->width}:{$r->height}");
                    })
                    ->toDisk('public')
                    ->save($playlistNameFile);

                // Удаляем старый плейлист (если есть)
                if (Storage::disk('public')->exists($playlistNameFile)) {
                    Storage::disk('public')->delete($playlistNameFile);
                }

                // Переименовываем в желаемый
                if (Storage::disk('public')->exists($playlistNameFinal)) {
                    Storage::disk('public')->move($playlistNameFinal, $playlistNameFile);
                    info("Плейлист переименован: {$playlistNameFinal} → {$playlistNameFile}");
                }

                $bandwidth = ($r->bitrate * 1000) + 128000;
                $average = intval($bandwidth * 0.9);

                $variants[] = [
                    'uri' => $playlistNameFile,
                    'bandwidth' => $bandwidth,
                    'average' => $average,
                    'resolution' => "{$r->width}x{$r->height}",
                    'codecs' => "avc1.640029,mp4a.40.2",
                    'name' => $label
                ];

                info("Готово: {$label}");
            }

            // --- Создаём мастер-файл ---
            $masterContent = ["#EXTM3U", "#EXT-X-VERSION:3"];
            foreach ($variants as $v) {
                $masterContent[] = "#EXT-X-STREAM-INF:BANDWIDTH={$v['bandwidth']},AVERAGE-BANDWIDTH={$v['average']},RESOLUTION={$v['resolution']},CODECS=\"{$v['codecs']}\"";
                $masterContent[] = $v['uri'];
            }

            $masterPath = "{$basename}.m3u8";
            Storage::disk('public')->put($masterPath, implode("\n", $masterContent) . "\n");

            info("Создан мастер-файл: {$masterPath}");

            // --- Удаляем исходный файл, если нужно ---
            // Storage::disk('public')->delete($this->fileName);

            $findFile->target = $masterPath;
            $findFile->processing = 0;
            $findFile->save();

            info("✅ Видео успешно обработано: {$findFile->target}");

        } catch (Exception $e) {
            info('❌ Ошибка при обработке видео: ' . $e->getMessage());
            if (isset($findFile)) {
                $findFile->processing = 0;
                $findFile->save();
            }
        }
    }
}