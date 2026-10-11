<?php

namespace App\Http\Resources;

use App\Enums\SkillLevel;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Fiche candidat complète (espace responsable). La liste du classement utilise une version allégée. */
class CandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = (new CandidateSummaryResource($this->resource))->toArray($request);

        return $summary + [
            'phone' => $this->phone,
            'linkedin_url' => $this->linkedin_url,
            'github_url' => $this->github_url,
            'portfolio_url' => $this->portfolio_url,
            'education_level' => self::enum($this->education_level),
            'education_field' => self::enum($this->education_field),
            'institution' => $this->institution,
            'graduation_year' => $this->graduation_year,
            'experience_years' => $this->experience_years,
            'experience_type' => self::enum($this->experience_type),
            'projects_description' => $this->projects_description,
            'motivation' => $this->motivation,
            'available_from' => $this->available_from?->format('Y-m-d'),
            'reviewer_note' => $this->reviewer_note,
            'scoring_version' => $this->scoring_version,
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'is_sought' => $skill->is_sought,
                'level' => self::enum(SkillLevel::from($skill->pivot->level)),
            ])->values()),
            'score_details' => $this->whenLoaded('scoreDetails', fn () => $this->scoreDetails->map(fn ($d) => [
                'criterion' => $d->criterion,
                'label' => $d->label,
                'points' => $d->points,
                'max_points' => $d->max_points,
                'explanation' => $d->explanation,
            ])->values()),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** Une valeur d'énumération exposée avec son libellé, pour que le frontend n'ait rien à traduire. */
    public static function enum(?BackedEnum $value): ?array
    {
        return $value ? ['value' => $value->value, 'label' => $value->label()] : null;
    }
}
