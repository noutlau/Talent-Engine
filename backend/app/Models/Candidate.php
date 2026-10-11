<?php

namespace App\Models;

use App\Enums\Availability;
use App\Enums\Category;
use App\Enums\EducationField;
use App\Enums\EducationLevel;
use App\Enums\ExperienceType;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'first_name', 'last_name', 'email', 'phone', 'city', 'country', 'linkedin_url', 'github_url', 'portfolio_url',
    'education_level', 'education_field', 'institution', 'graduation_year',
    'experience_years', 'experience_type', 'projects_description', 'motivation',
    'availability', 'available_from', 'review_status', 'reviewer_note',
])]
class Candidate extends Model
{
    protected $attributes = [
        'review_status' => 'nouveau',
    ];

    protected function casts(): array
    {
        return [
            'education_level' => EducationLevel::class,
            'education_field' => EducationField::class,
            'experience_type' => ExperienceType::class,
            'availability' => Availability::class,
            'category' => Category::class,
            'review_status' => ReviewStatus::class,
            'experience_years' => 'float',
            'graduation_year' => 'integer',
            'available_from' => 'date:Y-m-d',
            'score' => 'integer',
            'projects_points' => 'integer',
            'flags' => 'array',
        ];
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills')->withPivot('level');
    }

    public function scoreDetails(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)->orderBy('id'); // ordre du barème
    }

    /**
     * Ordre du classement : score, puis qualité des réalisations (le « faire » départage),
     * puis premier arrivé, puis identifiant pour un ordre toujours stable.
     */
    public const RANK_ORDER = 'score DESC, projects_points DESC, created_at ASC, id ASC';

    /**
     * Ajoute `rank_position` : la place du candidat dans le classement GLOBAL.
     * Calculée par une fonction de fenêtre SQL (MySQL 8, SQLite 3.25+), elle reste
     * juste même quand la liste est filtrée ou paginée.
     */
    public function scopeWithRank(Builder $query): Builder
    {
        $ranks = static::query()
            ->select('id')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY '.self::RANK_ORDER.') AS rank_position');

        return $query->select('candidates.*', 'ranks.rank_position')
            ->joinSub($ranks, 'ranks', 'ranks.id', '=', 'candidates.id')
            ->orderBy('ranks.rank_position');
    }
}
