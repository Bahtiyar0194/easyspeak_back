<?php
namespace App\Services;
use App\Models\School;

class SchoolService
{
    public function isAiSchoolDomain($school_id){
        $school = School::find($school_id);

        return $school->school_domain === 'ai';
    }
}