@php
    $colors = ['prioritaire' => '#059669', 'interessant' => '#0284c7', 'a_approfondir' => '#d97706', 'faible' => '#e11d48'];
    $color = $colors[$c['category']['value']] ?? '#4f46e5';
    $dots = ['debutant' => 1, 'intermediaire' => 2, 'avance' => 3];
    $date = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y') : null;
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Fiche candidat — {{ $c['first_name'] }} {{ $c['last_name'] }}</title>
<style>
    @page { margin: 28px 34px 50px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 10px; color: #1e293b; line-height: 1.45; }
    h1 { font-size: 20px; margin: 0; }
    h2 { font-size: 12px; margin: 0 0 8px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; color: #0f172a; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .muted { color: #64748b; }
    .brand { font-size: 11px; font-weight: bold; }
    .brand span { color: #4f46e5; }
    .header { margin: 10px 0 16px; padding-bottom: 12px; border-bottom: 3px solid {{ $color }}; }
    .score { text-align: right; }
    .score b { font-size: 30px; }
    .badge { display: inline-block; padding: 2px 9px; border-radius: 9px; color: #fff; font-weight: bold; font-size: 9.5px; background: {{ $color }}; }
    .section { margin-bottom: 14px; }
    .criteria td { padding: 5px 0 1px; }
    .criteria .explain td { padding: 0 0 6px; color: #64748b; font-size: 9px; border-bottom: 1px solid #f1f5f9; }
    .bar { height: 6px; background: #e2e8f0; border-radius: 3px; }
    .bar div { height: 6px; background: #4f46e5; border-radius: 3px; }
    .total td { padding-top: 7px; font-size: 12px; font-weight: bold; }
    .alert { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 7px 10px; border-radius: 4px; }
    .alert ul { margin: 3px 0 0; padding-left: 14px; }
    .info td { padding: 3px 0; }
    .info td.label { width: 105px; color: #64748b; }
    .dot { display: inline-block; width: 6px; height: 6px; border-radius: 3px; background: #e2e8f0; margin-left: 1px; }
    .dot.on { background: #4f46e5; }
    .text { white-space: pre-line; }
    .footer { position: fixed; bottom: -32px; left: 0; right: 0; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
</style>
</head>
<body>

<div class="footer">
    <table><tr>
        <td>Talent Engine · SKULLVI — Document confidentiel, usage interne. Le score aide à prioriser : la décision reste humaine.</td>
        <td style="text-align: right">Généré le {{ $generatedAt }}</td>
    </tr></table>
</div>

<div class="brand">Talent<span>Engine</span> <span class="muted" style="font-weight: normal">· Fiche candidat</span></div>

<table class="header"><tr>
    <td>
        <h1>{{ $c['first_name'] }} {{ $c['last_name'] }}</h1>
        <div class="muted" style="font-size: 11px">
            {{ collect([$c['city'], $c['country']])->filter()->implode(', ') }}
            · Rang {{ $c['rank'] }} · Candidature reçue le {{ $date($c['created_at']) }}
        </div>
    </td>
    <td class="score" style="width: 170px">
        <b>{{ $c['score'] }}</b><span class="muted" style="font-size: 13px">/100</span><br>
        <span class="badge">{{ $c['category']['label'] }}</span><br>
        <span class="muted">{{ $c['category']['action'] }}</span>
    </td>
</tr></table>

<table><tr>
    {{-- Colonne gauche : justification du score --}}
    <td style="width: 58%; padding-right: 16px">
        <div class="section">
            <h2>Détail du score</h2>
            <table class="criteria">
                @foreach ($c['score_details'] as $d)
                    <tr>
                        <td style="width: 42%; font-weight: bold">{{ $d['label'] }}</td>
                        <td style="padding-top: 8px"><div class="bar"><div style="width: {{ $d['max_points'] ? round(100 * $d['points'] / $d['max_points']) : 0 }}%"></div></div></td>
                        <td style="width: 44px; text-align: right; font-weight: bold">{{ $d['points'] }}<span class="muted" style="font-weight: normal">/{{ $d['max_points'] }}</span></td>
                    </tr>
                    <tr class="explain"><td colspan="3">{{ $d['explanation'] }}</td></tr>
                @endforeach
                <tr class="total"><td colspan="2">Total</td><td style="text-align: right">{{ $c['score'] }}/100</td></tr>
            </table>
            <div class="muted" style="font-size: 8px; margin-top: 3px">Règles de scoring v{{ $c['scoring_version'] }}</div>
        </div>

        @if (count($c['flags']))
            <div class="section alert">
                <b>Points d'attention</b>
                <ul>@foreach ($c['flags'] as $flag)<li>{{ $flag }}</li>@endforeach</ul>
            </div>
        @endif
    </td>

    {{-- Colonne droite : profil --}}
    <td style="width: 42%">
        <div class="section">
            <h2>Contact</h2>
            <table class="info">
                <tr><td class="label">Email</td><td>{{ $c['email'] }}</td></tr>
                <tr><td class="label">Téléphone</td><td>{{ $c['phone'] ?: '—' }}</td></tr>
                @foreach (['LinkedIn' => 'linkedin_url', 'GitHub' => 'github_url', 'Portfolio' => 'portfolio_url'] as $label => $key)
                    @if ($c[$key])<tr><td class="label">{{ $label }}</td><td style="word-break: break-all">{{ $c[$key] }}</td></tr>@endif
                @endforeach
            </table>
        </div>

        <div class="section">
            <h2>Parcours</h2>
            <table class="info">
                <tr><td class="label">Formation</td><td>{{ $c['education_level']['label'] }}<br><span class="muted">{{ $c['education_field']['label'] }}</span></td></tr>
                @if ($c['institution'] || $c['graduation_year'])
                    <tr><td class="label">Établissement</td><td>{{ collect([$c['institution'], $c['graduation_year']])->filter()->implode(', ') }}</td></tr>
                @endif
                <tr><td class="label">Expérience</td><td>{{ str_replace('.', ',', (string) $c['experience_years']) }} an(s) · {{ $c['experience_type']['label'] }}</td></tr>
                <tr><td class="label">Disponibilité</td><td>{{ $c['availability']['label'] }}{{ $c['available_from'] ? ' · dès le '.$date($c['available_from']) : ' · immédiate' }}</td></tr>
            </table>
        </div>

        <div class="section">
            <h2>Compétences</h2>
            <table class="info">
                @foreach ($c['skills'] as $s)
                    <tr>
                        <td>{{ $s['name'] }}@unless ($s['is_sought'])<span class="muted"> (hors critères)</span>@endunless</td>
                        <td style="text-align: right; white-space: nowrap" class="muted">
                            {{ $s['level']['label'] }}
                            @for ($i = 1; $i <= 3; $i++)<span class="dot {{ $i <= $dots[$s['level']['value']] ? 'on' : '' }}"></span>@endfor
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </td>
</tr></table>

<div class="section">
    <h2>Projets réalisés</h2>
    <div class="text">{{ $c['projects_description'] ?: 'Non renseigné.' }}</div>
</div>

<div class="section">
    <h2>Motivation</h2>
    <div class="text">{{ $c['motivation'] ?: 'Non renseignée.' }}</div>
</div>

<div class="section">
    <h2>Revue</h2>
    <table class="info">
        <tr><td class="label">Statut</td><td>{{ $c['review_status']['label'] }}</td></tr>
        <tr><td class="label">Note interne</td><td class="text">{{ $c['reviewer_note'] ?: '—' }}</td></tr>
    </table>
</div>

</body>
</html>
