<?php

namespace App\Services;

use App\Models\Candidate;
use App\Services\Scoring\CriterionScore;
use App\Services\Scoring\ScoreResult;
use App\Services\Scoring\ScoringEngine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Pipeline d'une candidature : enregistrer → associer les compétences → scorer → justifier.
 * Tout se fait dans une transaction : pas de candidat sans score ni de score sans justification.
 */
class CandidateService
{
    public function __construct(private readonly ScoringEngine $engine) {}

    /** @param  array  $data  données validées par StoreCandidateRequest */
    public function create(array $data): Candidate
    {
        return DB::transaction(function () use ($data) {
            $candidate = new Candidate(Arr::except($data, ['skills']));
            $candidate->forceFill($this->placeholderScore())->save();

            $candidate->skills()->sync(collect($data['skills'] ?? [])
                ->mapWithKeys(fn (array $s) => [$s['id'] => ['level' => $s['level']]])
                ->all());

            return $this->rescore($candidate);
        });
    }

    /** Recalcule et enregistre le score (après création, ou après un changement de règles). */
    public function rescore(Candidate $candidate): Candidate
    {
        return DB::transaction(function () use ($candidate) {
            $candidate->load('skills');
            $result = $this->withDuplicateFlag($this->engine->score($candidate), $candidate);

            $candidate->forceFill([
                'score' => $result->total,
                'category' => $result->category,
                'projects_points' => $result->points('projects'),
                'flags' => $result->flags,
                'scoring_version' => $result->version,
            ])->save();

            $candidate->scoreDetails()->delete();
            $candidate->scoreDetails()->createMany(array_map(fn (CriterionScore $d) => [
                'criterion' => $d->criterion,
                'label' => $d->label,
                'points' => $d->points,
                'max_points' => $d->max,
                'explanation' => mb_substr($d->explanation, 0, 500),
            ], $result->details));

            return $candidate->load('scoreDetails');
        });
    }

    private function withDuplicateFlag(ScoreResult $result, Candidate $candidate): ScoreResult
    {
        $samePhone = filled($candidate->phone) && Candidate::where('phone', $candidate->phone)
            ->whereKeyNot($candidate->getKey())
            ->exists();

        return $samePhone
            ? $result->withFlag('Doublon possible : ce numéro de téléphone figure dans une autre candidature.')
            : $result;
    }

    /** Colonnes obligatoires avant le premier calcul (remplacées dans la même transaction). */
    private function placeholderScore(): array
    {
        return ['score' => 0, 'category' => 'faible', 'scoring_version' => config('talent.version')];
    }
}
