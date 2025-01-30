<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Color;
use App\Models\Font;
use App\Models\Theme;
use App\Models\UploadConfiguration;
use App\Models\FaviconType;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class SchoolController extends Controller{

    public function __construct(Request $request){
        app()->setLocale($request->header('Accept-Language'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function get_school_attributes(Request $request){
        $attributes = new \stdClass();

        $school = School::leftJoin('types_of_subscription_plans', 'types_of_subscription_plans.subscription_plan_id', '=', 'schools.subscription_plan_id')
        ->select(
            'schools.*',
            'types_of_subscription_plans.subscription_plan_name'
        )
        ->where('school_id', '=', auth()->user()->school_id)
        ->first();

        $school->title_font_class = Font::where('font_id', '=', $school->title_font_id)->first()->font_class.'_title';
        $school->body_font_class = Font::where('font_id', '=', $school->body_font_id)->first()->font_class.'_body';
        $school->color_scheme_class = Color::where('color_id', '=', $school->color_id)->first()->color_class;

        if(time() >= strtotime($school->subscription_expiration_at)){
            $school->subscription_expired = true;
        }
        else{
            $school->subscription_expired = false;
        };

        $colors = Color::where('show_status_id', '=', 1)->orderBy('color_name')->get();
        $fonts = Font::where('show_status_id', '=', 1)->orderBy('font_name')->get();
        $themes = Theme::where('show_status_id', '=', 1)->get();

        $attributes->school = $school;
        $attributes->colors = $colors;
        $attributes->fonts = $fonts;
        $attributes->themes = $themes;

        return response()->json($attributes, 200);
    }


    public function set_school_attributes(Request $request){
        $validator = Validator::make($request->all(), [
            'theme_id' => 'required|numeric',
            'color_id' => 'required|numeric',
            'title_font_id' => 'required|numeric',
            'body_font_id' => 'required|numeric'
        ]);

        if($validator->fails()){
            return $this->json('error', 'Set attributes error', 422, $validator->errors());
        }

        $set_school = School::leftJoin('types_of_subscription_plans', 'types_of_subscription_plans.subscription_plan_id', '=', 'schools.subscription_plan_id')
        ->select(
            'schools.*',
            'types_of_subscription_plans.subscription_plan_name'
        )
        ->where('school_id', '=', auth()->user()->school_id)
        ->first();

        if(isset($set_school)){
            $old_color_id = $set_school->color_id;
            $old_title_font_id = $set_school->title_font_id;
            $old_body_font_id = $set_school->body_font_id;

            $set_school->theme_id = $request->theme_id;
            $set_school->color_id = $request->color_id;
            $set_school->title_font_id = $request->title_font_id;
            $set_school->body_font_id = $request->body_font_id;
            $set_school->save();

            $set_school->new_title_font_class = Font::where('font_id', '=', $set_school->title_font_id)->first()->font_class.'_title';
            $set_school->old_title_font_class = Font::where('font_id', '=', $old_title_font_id)->first()->font_class.'_title';
            $set_school->new_body_font_class = Font::where('font_id', '=', $set_school->body_font_id)->first()->font_class.'_body';
            $set_school->old_body_font_class = Font::where('font_id', '=', $old_body_font_id)->first()->font_class.'_body';
            $set_school->new_color_scheme_class = Color::where('color_id', '=', $request->color_id)->first()->color_class;
            $set_school->old_color_scheme_class = Color::where('color_id', '=', $old_color_id)->first()->color_class;

            if(time() >= strtotime($set_school->subscription_expiration_at)){
                $set_school->subscription_expired = true;
            }
            else{
                $set_school->subscription_expired = false;
            };
        }

        return response()->json($set_school, 200);
    }

    public function update(Request $request){
        $validator = Validator::make($request->all(), [
            'school_name' => 'required|string',
            'about' => 'nullable',
            'email' => 'nullable|email',
            'phone' => 'nullable|regex:/^((?!_).)*$/s',
            'instagram' => 'nullable|url',
            'facebook' => 'nullable|url',
            'tiktok' => 'nullable|url',
            'instagram' => 'nullable|url',
            'whatsapp' => 'nullable|regex:/^((?!_).)*$/s',
            'telegram' => 'nullable|url',
            'youtube' => 'nullable|url'
        ]);

        if($validator->fails()){
            return $this->json('error', 'Update school error', 422, $validator->errors());
        }

        $school = School::find(auth()->user()->school_id);
        $school->school_name = $request->school_name;
        $school->about = $request->about;
        $school->email = $request->email;
        $school->phone = $request->phone;
        $school->instagram = $request->instagram;
        $school->facebook = $request->facebook;
        $school->tiktok = $request->tiktok;
        $school->whatsapp = $request->whatsapp;
        $school->telegram = $request->telegram;
        $school->youtube = $request->youtube;
        $school->save();

        if(time() >= strtotime($school->subscription_expiration_at)){
            $school->subscription_expired = true;
        }
        else{
            $school->subscription_expired = false;
        };

        return response()->json([
            'school' => $school
        ], 200);
    }

    public function get_logo(Request $request){
        if($request->logo_variable == 'light_logo'){
            $school = School::where('light_theme_logo', '=', $request->logo_file)
            ->first();
            $path = storage_path('/app/schools/'.$school->school_id.'/logos/'.$school->light_theme_logo);
        }
        elseif($request->logo_variable == 'dark_logo'){
            $school = School::where('dark_theme_logo', '=', $request->logo_file)
            ->first();
            $path = storage_path('/app/schools/'.$school->school_id.'/logos/'.$school->dark_theme_logo);
        }

        if (!File::exists($path)) {
            return response()->json('Logo not found', 404);
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }

    public function upload_logo(Request $request){
        $material_type_id = 3;
        $max_image_file_size = UploadConfiguration::where('material_type_id', '=', $material_type_id)
        ->first()->max_file_size_mb;

        $validator = Validator::make($request->all(), [
            'logo_variable' => 'required|string',
            'logo_file' => 'required|file|mimes:jpg,jpeg,png,gif,svg,webp|max_mb:'.$max_image_file_size
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        $school = School::find(auth()->user()->school_id);

        if($request->logo_variable == 'light_logo'){
            $school_logo = $school->light_theme_logo;
        }
        elseif($request->logo_variable == 'dark_logo'){
            $school_logo = $school->dark_theme_logo;
        }

        if(isset($school_logo)){
            $path = storage_path('/app/schools/'.$school->school_id.'/logos/'.$school_logo);
            File::delete($path);
        }

        $file = $request->file('logo_file');
        $file_target = $file->hashName();
        $file->storeAs('schools/'.$school->school_id.'/logos/', $file_target);


        if($request->logo_variable == 'light_logo'){
            $school->light_theme_logo = $file_target;
        }
        elseif($request->logo_variable == 'dark_logo'){
            $school->dark_theme_logo = $file_target;
        }

        $school->save();

        return response()->json([
            'message' => 'Upload logo successful'
        ], 200);
    }

    public function delete_logo(Request $request){
        $school = School::find(auth()->user()->school_id);

        if($request->logo_variable == 'light_logo'){
            $school_logo = $school->light_theme_logo;
            $school->light_theme_logo = null;
        }
        elseif($request->logo_variable == 'dark_logo'){
            $school_logo = $school->dark_theme_logo;
            $school->dark_theme_logo = null;
        }

        if(isset($school_logo)){
            $path = storage_path('/app/schools/'.$school->school_id.'/logos/'.$school_logo);
            File::delete($path);
        }

        $school->save();

        return response()->json([
            'message' => 'Delete logo successful'
        ], 200);
    }

    public function get_favicon(Request $request){

        $path = storage_path('/app/schools/'.$request->school_id.'/favicons/'.$request->file_name);
        
        if (!File::exists($path)) {
            return response()->json('Favicon not found', 404);
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }


    public function upload_favicon(Request $request){
        $material_type_id = 3;
        $max_image_file_size = UploadConfiguration::where('material_type_id', '=', $material_type_id)
        ->first()->max_file_size_mb;

        $validator = Validator::make($request->all(), [
            'favicon_file' => 'required|file|mimes:jpg,jpeg,png,gif,svg,webp|max_mb:'.$max_image_file_size.'|dimensions:width=512,height=512'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        $school = School::find(auth()->user()->school_id);

        $path = storage_path('/app/schools/'.$school->school_id.'/favicons');
        File::deleteDirectory($path);

        $file = $request->file('favicon_file');

        $file_target = $file->hashName();
        $file->storeAs('schools/'.$school->school_id.'/favicons/', $file_target);

        $school->favicon = $file_target;

        $icons = FaviconType::get();

        foreach ($icons as $key => $icon) {
            $img = Image::make($file)->resize($icon->size, $icon->size);
            $img->save(storage_path('app/schools/'.$school->school_id.'/favicons/'.$icon->icon_name.'-'.$icon->size.'x'.$icon->size.'.png'), 80);
        }

        $school->save();

        return response()->json([
            'message' => 'Upload favicon successful'
        ], 200);
    }

    public function delete_favicon(Request $request){
        $school = School::find(auth()->user()->school_id);

        if(isset($school->favicon)){
            $path = storage_path('/app/schools/'.$school->school_id.'/favicons');
            File::deleteDirectory($path);
        }

        $school->favicon = null;
        $school->save();

        return response()->json([
            'message' => 'Delete favicon successful'
        ], 200);
    }
}
