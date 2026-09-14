<?php

namespace App\Http\Controllers;
use App\Models\Language;
use App\Models\User;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\UserQuizResult;


use Illuminate\Http\Request;

use App\Services\ConferenceService;
use App\Services\ScheduleService;
use App\Services\TaskService;
use App\Services\SchoolService;

class DashboardController extends Controller
{
    protected $conferenceService;
    protected $scheduleService;
    protected $taskService;
    protected $schoolService;

    public function __construct(Request $request, ScheduleService $scheduleService, ConferenceService $conferenceService, TaskService $taskService, SchoolService $schoolService)
    {
        $this->conferenceService = $conferenceService;
        $this->scheduleService = $scheduleService;
        $this->taskService = $taskService;
        $this->schoolService = $schoolService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get(Request $request)
    {
        $auth_user = auth()->user();

        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $dashboard = new \stdClass();


        $isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);

        $result = UserQuizResult::where('user_id', '=', $auth_user->user_id)
        ->latest('id')
        ->first();

        $course_id = 1;

        if (!$result || $result->is_completed != 1) {
            if($isOnlyLearner && $this->schoolService->isAiSchoolDomain($auth_user->school_id)){

                $level = CourseLevel::where('level_slug', '=', 'entry_test')
                ->where('course_id', '=', $course_id)
                ->first();

                if(isset($level)){
                    $section = CourseSection::where('level_id', '=', $level->level_id)
                    ->first();

                    if(isset($section)){
                        
                        $quiz_lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
                        ->leftJoin('quiz_levels', 'lessons.quiz_level_id', '=', 'quiz_levels.quiz_level_id')
                        ->leftJoin('quiz_levels_lang', 'quiz_levels.quiz_level_id', '=', 'quiz_levels_lang.quiz_level_id')
                        ->where('lessons.section_id', '=', $section->section_id)
                        ->where('quiz_levels_lang.lang_id', '=', $language->lang_id)
                        ->where('quiz_levels.course_id', '=', $course_id)
                        ->select(
                            'lessons.lesson_id',
                            'lessons.lesson_name',
                            'types_of_lessons.lesson_type_slug',
                            'quiz_levels.quiz_slug',
                            'quiz_levels.next_level_threshold',
                            'quiz_levels_lang.quiz_level_name',
                            'quiz_levels_lang.current_level_recommendation',
                            'quiz_levels_lang.next_level_recommendation'
                        )
                        ->distinct()
                        ->get();

                        foreach ($quiz_lessons as $key => $lesson) {
                            $lesson->tasks = $this->taskService->getLessonTasks($lesson->lesson_id, $language, true);
                        }

                        if(isset($quiz_lessons)){
                            $dashboard->quiz = [
                                'result' => $result,
                                'lessons' => $quiz_lessons,
                                'free_club_lessons_count' => $auth_user->free_club_lessons_count
                            ];
                        }
                    }
                }
            }
        }

        $for_dashboard = true;

        $dashboard->current_lessons = $this->conferenceService->getCurrentConferences($request);

        $dashboard->upcoming_lessons = $this->scheduleService->getSchedule($request, $auth_user->user_id, $language->lang_id, $for_dashboard, null);

        return response()->json($dashboard, 200);
    }

    public function save_quiz_result(Request $request)
    {
        $auth_user = auth()->user();

        $tasks = $this->taskService->getLessonTasksProgress($request->lesson_id);

        $completedTasksPercent = 0;

        foreach ($tasks as $key => $task) {
            $completedTasksPercent += $task->task_progress;
        }

        $deleteOldResult = UserQuizResult::where('user_id', $auth_user->user_id)
        ->where('lesson_id', $request->lesson_id)
        ->delete();

        $newQuizResult = UserQuizResult::firstOrCreate([
            'lesson_id' => $request->lesson_id,
            'user_id'   => $auth_user->user_id,
            'is_completed' => $request->is_completed,
            'progress' => count($tasks) > 0 ? $completedTasksPercent / count($tasks) : 0
        ]);

        return response()->json('success', 200);
    }
}
