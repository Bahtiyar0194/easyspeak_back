<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\LocationType;
use App\Models\Language;

use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(Request $request){
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get(Request $request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $locations = Location::leftJoin('locations_lang', 'locations_lang.location_id', '=', 'locations.location_id')
        ->leftJoin('types_of_locations', 'locations.location_type_id', '=', 'types_of_locations.location_type_id')
        ->leftJoin('types_of_locations_lang', 'types_of_locations.location_type_id', '=', 'types_of_locations_lang.location_type_id')
        ->where('locations_lang.lang_id', '=', $language->lang_id)
        ->where('types_of_locations_lang.lang_id', '=', $language->lang_id)
        ->distinct()
        ->select(
            'locations.location_id',
            'locations.parent_id',
            'locations_lang.location_name',
            'types_of_locations_lang.location_type_name'
        )
        ->orderBy('locations.location_id', 'asc')
        ->orderBy('locations_lang.location_name', 'desc')
        ->get();

        $locationTree = $this->buildTree($locations);

        return response()->json($locationTree, 200);
    }

    private function buildTree($locations, $parent_id = null, $level = 0)
    {
        $tree = [];

        foreach ($locations as $location) {
            if ($location->parent_id == $parent_id) {
                $children = $this->buildTree($locations, $location->location_id, $level + 1);
                $tree[] = [
                    'location_id' => $location->location_id,
                    'location_name' => $location->location_name,
                    'location_type_name' => $location->location_type_name,
                    'childs' => $children,
                    'level' => $level
                ];
            }
        }

        return $tree;
    }
}
