<?php

namespace App\Http\Controllers\Api;

use App\Enums\Availability;
use App\Enums\Category;
use App\Enums\EducationField;
use App\Enums\EducationLevel;
use App\Enums\ExperienceType;
use App\Enums\ReviewStatus;
use App\Enums\SkillLevel;
use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;

/** Listes de choix servies au frontend : une seule source de vérité, côté backend. */
class FormOptionsController extends Controller
{
    /** GET /api/skills */
    public function skills(): JsonResponse
    {
        return response()->json(Skill::orderBy('name')->get(['id', 'name', 'is_sought']));
    }

    /** GET /api/options */
    public function options(): JsonResponse
    {
        return response()->json([
            'education_levels' => EducationLevel::options(),
            'education_fields' => EducationField::options(),
            'experience_types' => ExperienceType::options(),
            'availabilities' => Availability::options(),
            'skill_levels' => SkillLevel::options(),
            'categories' => Category::options(),
            'review_statuses' => ReviewStatus::options(),
        ]);
    }
}
