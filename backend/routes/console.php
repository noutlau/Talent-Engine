<?php

use App\Models\Candidate;
use App\Services\CandidateService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('talent:rescore', function (CandidateService $service) {
    $count = 0;
    Candidate::query()->lazyById()->each(function (Candidate $candidate) use ($service, &$count) {
        $service->rescore($candidate);
        $count++;
    });
    $this->info("$count candidature(s) recalculée(s) avec les règles v".config('talent.version').'.');
})->purpose('Recalcule tous les scores après une modification de config/talent.php');
