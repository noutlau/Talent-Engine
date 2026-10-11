<?php

namespace App\Http\Controllers\Api;

use App\Enums\Availability;
use App\Enums\Category;
use App\Enums\EducationLevel;
use App\Enums\ReviewStatus;
use App\Exports\CandidatesCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCandidateRequest;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\CandidateSummaryResource;
use App\Models\Candidate;
use App\Services\CandidateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateController extends Controller
{
    public function __construct(private readonly CandidateService $service) {}

    /** POST /api/candidates — public : dépôt d'une candidature. */
    public function store(StoreCandidateRequest $request): JsonResponse
    {
        try {
            $this->service->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            // Double clic / envois simultanés : la contrainte UNIQUE de la base a tranché.
            throw ValidationException::withMessages(['email' => StoreCandidateRequest::DUPLICATE_EMAIL]);
        }

        // Le candidat ne voit pas son score : ce n'est pas une décision, juste un outil de tri interne.
        return response()->json(['message' => 'Votre candidature a bien été reçue.'], 201);
    }

    /** GET /api/candidates — liste classée, filtrable, paginée. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100']])['per_page'] ?? 25;

        return CandidateSummaryResource::collection(
            $this->filteredRanking($request)->paginate($perPage)->withQueryString()
        );
    }

    /** GET /api/candidates/export — le classement filtré, en CSV (mêmes filtres que la liste). */
    public function export(Request $request): StreamedResponse
    {
        $candidates = $this->filteredRanking($request)->with('skills', 'scoreDetails');

        return response()->streamDownload(function () use ($candidates) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : Excel reconnaît l'UTF-8 (accents)
            fputcsv($out, CandidatesCsv::HEADERS, ';', '"', '');
            foreach ($candidates->lazy(200) as $candidate) {
                fputcsv($out, CandidatesCsv::row($candidate), ';', '"', '');
            }
            fclose($out);
        }, 'candidatures-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** GET /api/candidates/{id}/pdf — fiche candidat imprimable. */
    public function pdf(int $id): Response
    {
        $candidate = Candidate::withRank()->with(['skills', 'scoreDetails'])->findOrFail($id);

        return Pdf::loadView('pdf.candidate', [
            'c' => (new CandidateResource($candidate))->resolve(),
            'generatedAt' => now()->format('d/m/Y à H:i').' UTC',
        ])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true) // n'embarque que les caractères utilisés : PDF léger
            ->download(sprintf('fiche-%s-%s.pdf', Str::slug($candidate->last_name), Str::slug($candidate->first_name)));
    }

    /** Applique les filtres de la requête au classement global (le rang reste le rang global). */
    private function filteredRanking(Request $request): Builder
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::enum(Category::class)],
            'min_score' => ['nullable', 'integer', 'between:0,100'],
            'skill' => ['nullable', 'integer'],
            'availability' => ['nullable', Rule::enum(Availability::class)],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'review_status' => ['nullable', Rule::enum(ReviewStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return Candidate::withRank()
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when(isset($filters['min_score']), fn ($q) => $q->where('score', '>=', $filters['min_score']))
            ->when($filters['skill'] ?? null, fn ($q, $v) => $q->whereHas('skills', fn ($s) => $s->where('skills.id', $v)))
            ->when($filters['availability'] ?? null, fn ($q, $v) => $q->where('availability', $v))
            ->when($filters['education_level'] ?? null, fn ($q, $v) => $q->where('education_level', $v))
            ->when($filters['review_status'] ?? null, fn ($q, $v) => $q->where('review_status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%$v%")
                ->orWhere('last_name', 'like', "%$v%")
                ->orWhere('email', 'like', "%$v%")
                ->orWhere('city', 'like', "%$v%")));
    }

    /** GET /api/candidates/ranking — tableau de bord : compteurs par catégorie + tête du classement. */
    public function ranking(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 10), 1), 100);
        $counts = Candidate::query()->selectRaw('category, COUNT(*) AS total')->groupBy('category')->pluck('total', 'category');

        return response()->json([
            'total' => (int) $counts->sum(),
            'categories' => array_map(fn (array $c) => $c + [
                'action' => Category::from($c['value'])->action(),
                'count' => (int) ($counts[$c['value']] ?? 0),
                'minimum' => config('talent.categories')[$c['value']],
            ], Category::options()),
            'top' => CandidateSummaryResource::collection(Candidate::withRank()->limit($limit)->get()),
        ]);
    }

    /** GET /api/candidates/{id} — fiche complète avec la justification du score. */
    public function show(int $id): CandidateResource
    {
        return new CandidateResource(Candidate::withRank()->with(['skills', 'scoreDetails'])->findOrFail($id));
    }

    /** PATCH /api/candidates/{id} — suivi de la revue par le responsable. */
    public function update(Request $request, int $id): CandidateResource
    {
        $data = $request->validate([
            'review_status' => ['sometimes', 'required', Rule::enum(ReviewStatus::class)],
            'reviewer_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        Candidate::findOrFail($id)->update($data);

        return $this->show($id);
    }
}
