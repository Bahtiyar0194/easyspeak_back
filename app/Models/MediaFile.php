<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
    use HasFactory;
    protected $table = 'files';
    protected $primaryKey = 'file_id';

    /**
     * Файл принадлежит школе загрузившего: библиотека школы не показывает
     * чужие файлы. Суперадмин загружает файлы платформы (school_id = NULL).
     * Заполняется здесь, чтобы не пропустить ни одно место создания файла
     */
    protected static function booted()
    {
        static::creating(function (MediaFile $file) {
            if ($file->school_id !== null) {
                return;
            }
            $user = auth()->user();
            if ($user && !$user->hasRole('super_admin')) {
                $file->school_id = $user->school_id;
            }
        });
    }
}
