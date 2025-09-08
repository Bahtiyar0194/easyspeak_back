<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\School;
use App\Models\Color;
use App\Models\Font;
use App\Models\FaviconType;
use App\Models\Payment;
use App\Models\Language;

class CheckSubdomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $origin = parse_url($request->header('Origin'));
        $host = str_replace('www.', '', $origin['host']);
        $parts = explode('.', $host);

        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        if (((count($parts) == 1 && $parts[0] == 'localhost') || (count($parts) == 2 && $parts[1] != 'localhost')) && $request->subdomain === null) {
            return response()->json('main', 200);
        } 
        else {
            if ($request->subdomain !== null) {
                $subdomain = $request->subdomain;
            } else {
                $subdomain = $parts[0];
            }

            $school = School::leftJoin('locations', 'schools.location_id', '=', 'locations.location_id')
            ->leftJoin('locations_lang', 'locations.location_id', '=', 'locations_lang.location_id')
            ->leftJoin('types_of_subscription_plans', 'types_of_subscription_plans.subscription_plan_id', '=', 'schools.subscription_plan_id')
            ->leftJoin('types_of_subscription_plans_lang', 'types_of_subscription_plans.subscription_plan_id', '=', 'types_of_subscription_plans_lang.subscription_plan_id')
            ->select(
                'schools.*',
                'locations.location_id',
                'locations_lang.location_name',
                'types_of_subscription_plans_lang.subscription_plan_name'
            )
            ->where('school_domain', '=', $subdomain)
            ->where('locations_lang.lang_id', '=', $language->lang_id)
            ->where('types_of_subscription_plans_lang.lang_id', '=', $language->lang_id)
            ->first();

            $icons = FaviconType::where('icon_name', '=', 'android-icon')
                ->get();

            $manifest_icons = [];

            if (isset($school->favicon)) {
                $base_url = url("/api/v1/school/get_favicon/" . $school->school_id);
            } else {
                $base_url = $request->header('Origin');
            }

            foreach ($icons as $icon) {
                array_push($manifest_icons, [
                    "src" => $base_url . '/android-icon-' . $icon['size'] . 'x' . $icon['size'] . '.png',
                    "sizes" => $icon['size'] . 'x' . $icon['size'],
                    "type" => "image/png"
                ]);
            }

            if (isset($school)) {
                $location = Location::with(['locations_lang' => function ($q) use ($language) {
                    $q->where('locations_lang.lang_id', $language->lang_id);
                }])->find($school->location_id);

                $names = [];
                $loc = $location;

                while ($loc) {
                    $name = $loc->locations_lang()
                        ->where('lang_id', $language->lang_id)
                        ->value('location_name');

                    if ($name) {
                        $names[] = $name;
                    }

                    $loc = $loc->parent;
                }

                $auth_user = auth('sanctum')->user();

                if($auth_user){
                    $isOwner = auth('sanctum')->user()->hasRole(['super_admin', 'school_owner', 'school_admin']);

                    if($isOwner){
                        $invoice = Payment::leftJoin('types_of_subscription_plans', 'types_of_subscription_plans.subscription_plan_id', '=', 'payments.subscription_plan_id')
                        ->leftJoin('types_of_subscription_plans_lang', 'types_of_subscription_plans.subscription_plan_id', '=', 'types_of_subscription_plans_lang.subscription_plan_id')
                        ->where('payments.school_id', '=', $school->school_id)
                        ->where('payments.is_paid', '=', 0)
                        ->where('payments.payment_method_id', '=', 1)
                        ->where('types_of_subscription_plans_lang.lang_id', '=', $language->lang_id)
                        ->select(
                            'payments.payment_id',
                            'payments.sum',
                            'payments.description',
                            'payments.created_at',
                            'types_of_subscription_plans_lang.subscription_plan_name',
                        )
                        ->first();

                        if(isset($invoice)){
                            $school->invoice = $invoice;
                        }
                    }
                }

                $school->location_full = implode(', ', array_reverse(array_filter($names)));

                $school->title_font_class = Font::where('font_id', '=', $school->title_font_id)->first()->font_class . '_title';
                $school->body_font_class = Font::where('font_id', '=', $school->body_font_id)->first()->font_class . '_body';
                $school->color_scheme_class = Color::where('color_id', '=', $school->color_id)->first()->color_class;
                $school->favicons = FaviconType::get();
                $school->manifest_icons = $manifest_icons;

                if (time() >= strtotime("+7 day", strtotime($school->subscription_expiration_at))) {
                    $school->subscription_expired = true;
                } else {
                    $school->subscription_expired = false;
                }

                return response()->json($school, 200);
            } else {
                return response()->json('School not found', 404);
            }
        }
    }
}
