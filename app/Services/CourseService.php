<?php
namespace App\Services;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\Conference;
use App\Models\ConferenceMember;
use App\Models\Language;

class CourseService
{

    public function getCourses($request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $courses = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses.course_name_slug',
            'courses_lang.course_name'
        )
        ->distinct()
        ->orderBy('courses.course_id', 'asc')
        ->get();

        return $courses;
    }

    public function getCourse($course_slug, $language_id){
        $course = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.course_name_slug', '=', $course_slug)
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->first();

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        return $course;
    }

    public function getCourseLevels($course_id, $language_id){
        $levels = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->where('course_levels.course_id', '=', $course_id)
        ->where('course_levels_lang.lang_id', '=', $language_id)
        ->select(
            'course_levels.level_id',
            'course_levels.level_slug',
            'course_levels.is_available_always',
            'course_levels_lang.level_name',
            'courses.course_name_slug'
        )
        ->distinct()
        ->orderBy('course_levels.level_id', 'asc')
        ->get();

        if (count($levels) == 0) {
            return response()->json(['error' => 'Levels not found'], 404);
        }

        return $levels;
    }

    public function getCourseLevel($course_id, $level_slug, $language_id){
        $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->where('course_levels.course_id', '=', $course_id)
        ->where('course_levels.level_slug', '=', $level_slug)
        ->where('course_levels_lang.lang_id', '=', $language_id)
        ->select(
            'course_levels.level_id',
            'course_levels.level_slug',
            'course_levels.is_available_always',
            'course_levels_lang.level_name',
            'courses.course_name_slug'
        )
        ->first();

        if (!isset($level)) {
            return response()->json(['error' => 'Level not found'], 404);
        }

        return $level;
    }

    public function getLevelSections($level_id){
        $sections = CourseSection::where('level_id', '=', $level_id)
        ->select(
            'section_id',
            'section_name'
        )
        ->orderBy('sort_num', 'asc')
        ->get();

        return $sections;
    }

    public function levelIsAvailable($level_id, $user_id){
        // получаем пользователя по ID
        $user = User::find($user_id);

        if (!isset($user)) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $isOnlyLearner = $user->hasOnlyRoles(['learner']);

        if(!$isOnlyLearner){
            return true;
        }
        else{
            $course_level = CourseLevel::leftJoin('groups', 'groups.level_id', '=', 'course_levels.level_id')
            ->leftJoin('group_members', 'group_members.group_id', '=', 'groups.group_id')
            ->where('course_levels.level_id', '=', $level_id)
            ->where('group_members.member_id', '=', $user->user_id)
            ->first();

            if(isset($course_level)){
                return true;
            }
            else{
                return false;
            }
        }

//Для сплитования
            //         $course_level = CourseLevel::leftJoin('groups', 'groups.level_id', '=', 'course_levels.level_id')
            // ->leftJoin('group_members', 'group_members.group_id', '=', 'groups.group_id')
            // ->where('course_levels.level_id', '=', $level_id)
            // ->where('group_members.member_id', '=', $user->user_id)
            // ->where('groups.status_type_id', '=', 1)
            // ->select(
            //     'group_members.subscription_expiration_at'
            // )
            // ->first();

            // if(isset($course_level)){
            //     //Когда истек срок подписки
            //     if (time() >= strtotime($course_level->subscription_expiration_at)) {
            //         $course_level->subscription_expires = true;
            //     } else {
            //         $course_level->subscription_expires = false;
            //     }

            //     //Даем еще 7 дней на оплату (при окончательном истечении подписки доступ к курсу закрыт) 
            //     if (time() >= strtotime("+7 day", strtotime($course_level->subscription_expiration_at))) {
            //         $course_level->subscription_expired = true;
            //     } else {
            //         $course_level->subscription_expired = false;
            //     }

            //     return $course_level;
            // }
            // else{
            //     return false;
            // }
    }

    public function lessonIsAvailable($lesson, $is_available_always){
        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);

        if(!$isOnlyLearner){
            return true;
        }
        else{
            if($is_available_always === 1){
                return true;
            }
            else{
                $conferenceLesson = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
                ->where('lessons.section_id', '=', $lesson->section_id)
                ->where('lessons.sort_num', '<=', $lesson->sort_num)
                ->whereIn('types_of_lessons.lesson_type_slug', ['conference', 'file_test'])
                ->orderBy('lessons.sort_num', 'desc')
                ->first();

                if(isset($conferenceLesson)){
                    $conference = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
                    ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
                    ->select(
                        'conferences.conference_id'
                    )
                    ->where('conferences.participated', '>=', 2)
                    ->where('conferences.lesson_id', '=', $conferenceLesson->lesson_id)
                    ->where('group_members.member_id', '=', $auth_user->user_id)
                    ->first();

                    if(isset($conference)){
                        return true;
                    }

                    return false;
                }
                return true;
            }

        }
    }

    public function getLessons($section_id, $language_id){
        $lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
        ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
        ->where('lessons.section_id', '=', $section_id)
        ->where('types_of_lessons_lang.lang_id', '=', $language_id)
        ->select(
            'lessons.lesson_id',
            'lessons.section_id',
            'lessons.sort_num',
            'lessons.lesson_name',
            'types_of_lessons.lesson_type_id',
            'types_of_lessons.lesson_type_slug',
            'types_of_lessons_lang.lesson_type_name'
        )
        ->distinct()
        ->orderBy('lessons.sort_num', 'asc')
        ->get();

        return $lessons;
    }

    public function getLessonMaterials($lesson_id, $language){
        $lesson_materials = LessonMaterial::leftJoin('files', 'lesson_materials.file_id', '=', 'files.file_id')
        ->leftJoin('types_of_materials as file_types', 'files.material_type_id', '=', 'file_types.material_type_id')
        ->leftJoin('types_of_materials_lang as file_types_lang', function ($join) use ($language) {
            $join->on('file_types.material_type_id', '=', 'file_types_lang.material_type_id')
                 ->where('file_types_lang.lang_id', '=', $language->lang_id);
        })
        ->leftJoin('blocks', 'lesson_materials.block_id', '=', 'blocks.block_id')
        ->leftJoin('types_of_materials as block_types', 'blocks.material_type_id', '=', 'block_types.material_type_id')
        ->leftJoin('types_of_materials_lang as block_types_lang', function ($join) use ($language) {
            $join->on('block_types.material_type_id', '=', 'block_types_lang.material_type_id')
                 ->where('block_types_lang.lang_id', '=', $language->lang_id);
        })
        ->select(
            'lesson_materials.lesson_material_id',
            'lesson_materials.annotation',
            'files.target',
            'blocks.content',
            'blocks.options',
            'file_types.material_type_slug as file_material_type_slug',
            'file_types_lang.material_type_name as file_material_type_name',
            'file_types.material_type_category as file_material_type_category',
            'file_types.icon as file_icon',
            'block_types.material_type_slug as block_material_type_slug',
            'block_types_lang.material_type_name as block_material_type_name',
            'block_types.material_type_category as block_material_type_category',
            'block_types.icon as block_icon',
            'lesson_materials.sort_num'
        )
        ->where('lesson_materials.lesson_id', '=', $lesson_id)
        ->orderBy('lesson_materials.sort_num', 'asc')
        ->groupBy(
            'lesson_materials.lesson_material_id', 
            'lesson_materials.annotation',
            'files.target',
            'blocks.content',
            'blocks.options',
            'file_material_type_slug',
            'file_material_type_name',
            'file_material_type_category',
            'file_icon',
            'block_material_type_slug',
            'block_material_type_name',
            'block_material_type_category',
            'block_icon',
            'lesson_materials.sort_num'
        ) // Группировка по ID материала
        ->get();

        return $lesson_materials;
    }
}
?>