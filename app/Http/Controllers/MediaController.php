<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Iman\Streamer\VideoStreamer;
use App\Models\MaterialType;
use App\Models\Language;
use App\Models\MediaFile;
use App\Models\UploadConfiguration;
use File;
use Storage;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Services\UploadFileService;

class MediaController extends Controller
{
    protected $uploadFileService;

    public function __construct(Request $request, UploadFileService $uploadFileService)
    {
        $this->uploadFileService = $uploadFileService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_attributes(Request $request){    
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $totalDiskSpace = disk_total_space("/");
        $freeDiskSpace = disk_free_space("/");
        $usedDiskSpace = $totalDiskSpace - $freeDiskSpace;

        $disk = new \stdClass();
        $disk->total_space = round($totalDiskSpace / (1024 ** 3));
        $disk->free_space = round($freeDiskSpace / (1024 ** 3));
        $disk->used_space = round($usedDiskSpace / (1024 ** 3));
        
        $all_file_types = MaterialType::leftJoin('types_of_materials_lang', 'types_of_materials.material_type_id', '=', 'types_of_materials_lang.material_type_id')
        ->select(
            'types_of_materials.material_type_id',
            'types_of_materials.material_type_slug',
            'types_of_materials.icon',
            'types_of_materials_lang.material_type_name'
        )
        ->where('types_of_materials.show_status_id', '=', 1)
        ->where('types_of_materials.material_type_category', '=', 'file')
        ->where('types_of_materials_lang.lang_id', '=', $language->lang_id)
        ->distinct()
        ->orderBy('types_of_materials.material_type_id', 'asc')
        ->get();

        $attributes = new \stdClass();
        $attributes->disk = $disk;
        $attributes->all_file_types = $all_file_types;

        return response()->json($attributes, 200);
    }

    public function get_files(Request $request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем параметры лимита на страницу
        $per_page = $request->per_page ? $request->per_page : 10;
        // Получаем параметры сортировки
        $sortKey = $request->input('sort_key', 'created_at');  // Поле для сортировки по умолчанию
        $sortDirection = $request->input('sort_direction', 'asc');  // Направление по умолчанию

        $files = MediaFile::leftJoin('types_of_materials', 'files.material_type_id', '=', 'types_of_materials.material_type_id')
        ->leftJoin('types_of_materials_lang', 'types_of_materials.material_type_id', '=', 'types_of_materials_lang.material_type_id')
        ->select(
            'files.file_id',
            'files.file_name',
            'files.target',
            'files.size',
            'files.material_type_id',
            'files.created_at',
            'types_of_materials.icon',
            'types_of_materials.material_type_slug',
            'types_of_materials_lang.material_type_name'
        )
        ->where('types_of_materials_lang.lang_id', '=', $language->lang_id)
        ->distinct()
        ->orderBy($sortKey, $sortDirection);

        // Применяем фильтрацию по параметрам из запроса
        $file_name = $request->file_name;
        $material_type_slug = $request->material_type_slug;
        $material_types_id = $request->material_types;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;

        // Фильтрация по названию файла
        if (!empty($file_name)) {
            $files->where('files.file_name', 'LIKE', '%' . $file_name . '%');
        }

        // Фильтрация по типу файла
        if (!empty($material_type_slug)) {
            $files->where('types_of_materials.material_type_slug', '=', $material_type_slug);
        }

        // Фильтрация по типу файлов
        if (!empty($material_types_id)) {
            $files->whereIn('files.material_type_id', $material_types_id);
        }

        // Фильтрация по дате создания
        if ($created_at_from && $created_at_to) {
            $files->whereBetween('files.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:59']);
        } elseif ($created_at_from) {
            $files->where('files.created_at', '>=', $created_at_from . ' 00:00:00');
        } elseif ($created_at_to) {
            $files->where('files.created_at', '<=', $created_at_to . ' 23:59:59');
        }

        // Возвращаем пагинированный результат
        return response()->json($files->paginate($per_page)->onEachSide(1), 200);
    }

    public function get_file(Request $request)
    {
        $fileName = $request->file_name;

        $path = storage_path('app/public/' . $fileName);
        
        if (!File::exists($path)) {
            return response()->json(['status' => 'error', 'message' => 'File not found'], 404);
        }

        if (pathinfo($fileName, PATHINFO_EXTENSION) === 'm3u8') {
            // Если HLS готов — возвращаем сам master.m3u8
            return response()->file($path, [
                'Content-Type' => 'application/vnd.apple.mpegurl',
            ]);
        }

        $type = File::mimeType($path);
        $typeParts = explode('/', $type);

        // Если видео
        if (isset($typeParts[0]) && $typeParts[0] === 'video') {
            $response = VideoStreamer::streamFile($path);
        } 
        else {
            // Любой другой тип файла (например, изображение)
            $file = File::get($path);
            $response = Response::make($file, 200);
        }

        $response->header("Content-Type", $type);
        return $response;
    }

    public function add_file(Request $request){
        $rules = [
            'file_name' => 'required|string|min:2',
            'material_type_id' => 'required|numeric',
            'upload_file' => 'required|file'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $material_type = MaterialType::where('material_type_id', '=', $request->material_type_id)
        ->first();

        if (!$material_type) {
            return response()->json(['error' => 'Material type is not found'], 404);
        }

        $upload_config = UploadConfiguration::leftJoin('types_of_materials', 'upload_configuration.material_type_id', '=', 'types_of_materials.material_type_id')
        ->where('types_of_materials.material_type_slug', '=', $material_type->material_type_slug)
        ->select(
            'upload_configuration.max_file_size_mb',
            'upload_configuration.mimes'
        )
        ->first();
        
        $rules['upload_file'] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $file = $request->file('upload_file');

        if($file){
            $file_name = $file->hashName();

            $this->uploadFileService->uploadFile($file, $file_name, $material_type->material_type_slug);

            $new_file = new MediaFile();
            $new_file->file_name = $request->file_name;
            $new_file->target = $file_name;
            $new_file->size = $file->getSize() / 1048576;
            $new_file->material_type_id = $material_type->material_type_id;
            $new_file->save();

            return response()->json('success', 200);
        }

        return response()->json('no_file', 400);
    }

    public function replace_file(Request $request){
        $rules = [
            'upload_file' => 'required|file'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $findFile = MediaFile::findOrFail($request->file_id);

        $material_type = MaterialType::where('material_type_id', '=', $findFile->material_type_id)
        ->first();

        if (!$material_type) {
            return response()->json(['error' => 'Material type is not found'], 404);
        }

        $upload_config = UploadConfiguration::leftJoin('types_of_materials', 'upload_configuration.material_type_id', '=', 'types_of_materials.material_type_id')
        ->where('types_of_materials.material_type_slug', '=', $material_type->material_type_slug)
        ->select(
            'upload_configuration.max_file_size_mb',
            'upload_configuration.mimes'
        )
        ->first();
        
        $rules['upload_file'] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $file = $request->file('upload_file');

        if($file){
            $file_name = $file->hashName();

            $this->uploadFileService->uploadFile($file, $file_name, $material_type->material_type_slug);

            $fileBaseName = pathinfo($findFile->target, PATHINFO_FILENAME);

            $files = Storage::allFiles('public'); // включает подпапки

            foreach ($files as $f) {
                if (str_starts_with(basename($f), $fileBaseName)) {
                    Storage::delete($f);
                }
            }

            $findFile->target = $file_name;
            $findFile->size = $file->getSize() / 1048576;
            $findFile->save();

            return response()->json($findFile, 200);
        }
    }

    public function check_video(Request $request){
        $video = MediaFile::where('target', 'LIKE', $request->file_name . '%')
        ->select(
            'processing',
            'target'
        )
        ->first();

        if (!isset($video)) {
            return response()->json(['status' => 'error', 'message' => 'File not found in DB'], 404);
        }

        $fileName = $request->file_name;

        $path = storage_path('app/public/' . $fileName);

        if (!File::exists($path)) {
            return response()->json(['status' => 'error', 'message' => 'File not found in public folder'], 404);
        }

        return response()->json($video, 200);
    }
}
