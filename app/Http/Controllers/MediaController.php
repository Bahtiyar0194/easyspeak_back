<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use File;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_file(Request $request){    
        $path = storage_path('/app/public/'.$request->file_name);
    
        if (!File::exists($path)) {
            return response()->json('Image not found', 404);
        }
    
        $file = File::get($path);
        $type = File::mimeType($path);
    
        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);
    
        return $response;
    }
}
