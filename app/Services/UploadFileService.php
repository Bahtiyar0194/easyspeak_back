<?php
namespace App\Services;
use Image;
use Storage;

use App\Jobs\ProcessVideoJob;

class UploadFileService
{
    public function uploadFile($file, $file_name, $material_type_slug){
        if($material_type_slug == 'image'){
            $resized_image = Image::make($file)->resize(500, null, function ($constraint) {
                $constraint->aspectRatio();
            })->stream('png', 80);
            Storage::disk('local')->put('/public/'.$file_name, $resized_image);
        }
        else{
            $file->storeAs('/public/', $file_name);

            if ($material_type_slug == 'video') {
                ProcessVideoJob::dispatch($file_name);
            }
        }
    }
}
?>