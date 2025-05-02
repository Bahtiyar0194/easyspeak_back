<?php
namespace App\Services;
use App\Models\CourseLevel;

class CourseService
{
    public function levelIsAvailable($level_id){
        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        //$isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);


        if($auth_user->hasRole(['school_owner', 'school_admin', 'mentor'])){
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
}
?>