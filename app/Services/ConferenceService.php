<?php
namespace App\Services;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Conference;
use App\Models\Group;
use Str;
use Carbon\Carbon;

class ConferenceService
{
    public function createConference($group_id, $lesson_id, $start_time, $end_time){

        $group = Group::findOrFail($group_id);

        $conference = new Conference();
        $conference->uuid = str_replace('-', '', (string) Str::uuid());
        $conference->group_id = $group_id;
        $conference->lesson_id = $lesson_id;
        $conference->operator_id = auth()->user()->user_id;
        $conference->mentor_id = $group->mentor_id;
        $conference->start_time = $start_time;
        $conference->end_time = $end_time;
        $conference->save();

        return $conference;
    }

    public function createConferences($group_id, $level_id, $start_time){
        Conference::where('group_id', $group_id)
        ->delete();

        // Начальная дата в объекте Carbon
        $nextStartTime = Carbon::parse($start_time);

        $sections = CourseSection::where('level_id', '=', $level_id)
        ->select(
            'section_id'
        )
        ->orderBy('section_id', 'asc')
        ->get();

        foreach ($sections as $s => $section) {
            $lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
            ->where('lessons.section_id', '=', $section->section_id)
            ->whereIn('types_of_lessons.lesson_type_slug', ['conference', 'file_test'])
            ->select(
                'lessons.lesson_id',
                'lessons.sort_num'
            )
            ->distinct()
            ->orderBy('lessons.sort_num', 'asc')
            ->get();

            foreach ($lessons as $key => $lesson) {
                $new_conference = $this->createConference($group_id, $lesson->lesson_id, $nextStartTime->format('Y-m-d H:i:s'), $nextStartTime->copy()->addHours(2)->format('Y-m-d H:i:s'));

                // Прибавляем 7 дней для следующей конференции
                $nextStartTime->addDays(7);
            }
        }
    }
}
?>