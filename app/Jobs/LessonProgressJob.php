<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Lesson;
use App\Models\CompletedTask;
use App\Models\LessonProgress;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Services\SchoolService;

class LessonProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void {

        $lessons = Lesson::with([
            'tasks',
            'tasks.taskOption'
        ])->get();

        $completedTasks = CompletedTask::all()
            ->groupBy(['learner_id', 'task_id']);

        User::chunk(100, function ($users) use ($lessons, $completedTasks) {

            foreach ($users as $user) {

                $isAiSchool = app(SchoolService::class)
                    ->isAiSchoolDomain($user->school_id);

                foreach ($lessons as $lesson) {

                    $totalProgress = 0;
                    $includedTasks = 0;

                    // Удаляем старый прогресс по уроку 
                    LessonProgress::where('lesson_id', '=', $lesson->lesson_id)
                    ->where('learner_id', '=', $user->user_id)
                    ->delete();

                    foreach ($lesson->tasks as $task) {

                        $taskProgresses =
                            $completedTasks[$user->user_id][$task->task_id]
                            ?? collect();

                        $showOnPlatform =
                            $task->taskOption->show_on_platform;

                        $canCountProgress =
                            $showOnPlatform === 'both'
                            || ($isAiSchool && $showOnPlatform === 'b2c')
                            || (!$isAiSchool && $showOnPlatform === 'b2b');

                        if (!$canCountProgress) {
                            continue;
                        }

                        $includedTasks++;

                        foreach ($taskProgresses as $completedTask) {
                            $totalProgress += $completedTask->progress;
                        }
                    }

                    // защита от деления на 0
                    if ($includedTasks === 0) {
                        continue;
                    }

                    $progress = $totalProgress / $includedTasks;

                    if ($progress <= 0) {
                        continue;
                    }

                    $new_lesson_progress = new LessonProgress(); 
                    $new_lesson_progress->lesson_id = $lesson->lesson_id; 
                    $new_lesson_progress->learner_id = $user->user_id; 
                    $new_lesson_progress->progress = $progress; 
                    $new_lesson_progress->save();
                }
            }
        });
    }
}