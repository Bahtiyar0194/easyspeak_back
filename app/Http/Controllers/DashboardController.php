<?php

namespace App\Http\Controllers;
use App\Models\Language;

use Illuminate\Http\Request;

use App\Services\ConferenceService;
use App\Services\ScheduleService;

class DashboardController extends Controller
{
    protected $conferenceService;
    protected $scheduleService;

    public function __construct(Request $request, ScheduleService $scheduleService, ConferenceService $conferenceService)
    {
        $this->conferenceService = $conferenceService;
        $this->scheduleService = $scheduleService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get(Request $request)
    {
        $auth_user = auth()->user();

        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $dashboard = new \stdClass();

        $for_dashboard = true;

        $dashboard->current_lessons = $this->conferenceService->getCurrentConferences($request);

        $dashboard->upcoming_lessons = $this->scheduleService->getSchedule($request, $auth_user->user_id, $language->lang_id, $for_dashboard, null);

        return response()->json($dashboard, 200);
    }
}
