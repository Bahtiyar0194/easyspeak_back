<?php

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG_PATH', 'C:\ffmpeg\bin\ffmpeg.exe'),
        'threads' => 2,
        'timeout' => 3600, // 1 час
    ],

    'ffprobe' => [
        'binaries' => env('FFPROBE_PATH', 'C:\ffmpeg\bin\ffprobe.exe'),
    ],

    'timeout' => 3600,

    'enable_logging' => true,

    'set_command_and_error_output_on_exception' => false,

    'temporary_files_root' => env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir()),
];
