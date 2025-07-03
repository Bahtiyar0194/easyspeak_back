<?php
namespace App\Services;
use App\Models\CourseLevel;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\Conference;
use App\Models\ConferenceMember;

class CourseService
{
    public function levelIsAvailable($level_id){
        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);


        if(!$isOnlyLearner){
            return true;
        }
        else{
            $course_level = CourseLevel::leftJoin('groups', 'groups.level_id', '=', 'course_levels.level_id')
            ->leftJoin('group_members', 'group_members.group_id', '=', 'groups.group_id')
            ->where('course_levels.level_id', '=', $level_id)
            ->where('group_members.member_id', '=', $auth_user->user_id)
            ->first();

            if(isset($course_level)){
                return true;
            }
            else{
                return false;
            }
        }
    }

    public function lessonIsAvailable($lesson){
        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);

        if(!$isOnlyLearner){
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