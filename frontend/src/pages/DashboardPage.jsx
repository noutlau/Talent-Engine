import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, download } from '../api'
import { Alert, CATEGORY_STYLES, CategoryBadge, DownloadButton, inputClass, ScoreBar, Spinner } from '../components/ui'

const ACTION_CLASS = 'rounded-md border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:border-brand-500 hover:text-brand-600 disabled:opacity-50'

const FILTERS = ['category', 'min_score', 'skill', 'availability', 'education_level', 'review_status', 'search', 'page']

export default function DashboardPage() {
  // Les filtres vivent dans l'URL : un lien partagé ou un retour arrière conserve la vue.
  const [params, setParams] = useSearchParams()
  const filters = Object.fromEntries(FILTERS.map((key) => [key, params.get(key) ?? '']))
  const [stats, setStats] = useState(null)
  const [options, setOptions] = useState(null)
  const [skills, setSkills] = useState([])
  const [list, setList] = useState(null)
  const [error, setError] = useState(null)
  const [search, setSearch] = useState(filters.search)

  useEffect(() => {
    Promise.all([api('/candidates/ranking', { query: { limit: 1 } }), api('/options'), api('/skills')])
      .then(([ranking, opts, skillList]) => {
        setStats(ranking)
        setOptions(opts)
        setSkills(skillList)
      })
      .catch((e) => setError(e.message))
  }, [])

  const query = params.toString()
  useEffect(() => {
    let cancelled = false
    api('/candidates', { query: Object.fromEntries(new URLSearchParams(query)) })
      .then((data) => !cancelled && setList(data))
      .catch((e) => !cancelled && setError(e.message))
    return () => { cancelled = true }
  }, [query])

  // Recherche : on attend que la saisie se calme avant d'interroger l'API.
  useEffect(() => {
    const timer = setTimeout(() => search !== filters.search && setFilter('search', search), 300)
    return () => clearTimeout(timer)
  })

  function setFilter(key, value) {
    const next = new URLSearchParams(params)
    if (value === '') next.delete(key)
    else next.set(key, value)
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  if (error) return <Alert>{error}</Alert>
  if (!stats || !options) return <Spinner />

  const select = (key, choices, placeholder) => (
    <select className={`${inputClass} mt-0`} value={filters[key]} onChange={(e) => setFilter(key, e.target.value)} aria-label={placeholder}>
      <option value="">{placeholder}</option>
      {choices.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
    </select>
  )

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Classement des candidatures</h1>
          <p className="text-sm text-slate-500">Le score aide à prioriser la lecture : la décision reste humaine.</p>
        </div>
        {/* L'export reprend exactement les filtres affichés (sauf la pagination : toutes les lignes sont exportées). */}
        <DownloadButton
          title="Exporter les candidatures affichées (filtres compris)"
          onDownload={() => download('/candidates/export', { query: { ...filters, page: '' }, fallbackName: 'candidatures.csv' })}>
          ⬇ Exporter en CSV{list && ` (${list.meta.total})`}
        </DownloadButton>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <button onClick={() => setFilter('category', '')}
          className={`rounded-xl border bg-white p-4 text-left shadow-sm transition hover:border-brand-500 ${!filters.category ? 'border-brand-500 ring-2 ring-brand-500/20' : 'border-slate-200'}`}>
          <div className="text-3xl font-bold">{stats.total}</div>
          <div className="text-sm text-slate-500">Candidatures</div>
        </button>
        {stats.categories.map((c) => (
          <button key={c.value} onClick={() => setFilter('category', filters.category === c.value ? '' : c.value)}
            className={`rounded-xl border bg-white p-4 text-left shadow-sm transition hover:border-brand-500 ${filters.category === c.value ? 'border-brand-500 ring-2 ring-brand-500/20' : 'border-slate-200'}`}>
            <div className="flex items-baseline justify-between">
              <span className="text-3xl font-bold">{c.count}</span>
              <span aria-hidden>{CATEGORY_STYLES[c.value].icon}</span>
            </div>
            <div className="text-sm font-medium text-slate-700">{c.label}</div>
            <div className="text-xs text-slate-500">{c.minimum > 0 ? `score ≥ ${c.minimum}` : 'score < 50'} · {c.action}</div>
          </button>
        ))}
      </div>

      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <input className={`${inputClass} mt-0 lg:col-span-2`} type="search" placeholder="Rechercher un nom, un email, une ville…" value={search} onChange={(e) => setSearch(e.target.value)} />
        {select('skill', skills.map((s) => ({ value: String(s.id), label: s.name })), 'Toutes compétences')}
        {select('availability', options.availabilities, 'Toute disponibilité')}
        {select('education_level', options.education_levels, "Tout niveau d'études")}
        {select('review_status', options.review_statuses, 'Tout statut de revue')}
      </div>

      {!list ? <Spinner /> : list.data.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
          Aucune candidature ne correspond à ces critères.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3">Rang</th>
                <th className="px-4 py-3">Candidat</th>
                <th className="px-4 py-3">Score</th>
                <th className="px-4 py-3">Catégorie</th>
                <th className="px-4 py-3">Alertes</th>
                <th className="px-4 py-3">Revue</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {list.data.map((c) => (
                <tr key={c.id} className="hover:bg-slate-50">
                  <td className="px-4 py-3 font-bold text-slate-400">{c.rank}</td>
                  <td className="px-4 py-3">
                    <Link to={`/dashboard/candidates/${c.id}`} className="font-semibold text-slate-900 hover:text-brand-600">{c.first_name} {c.last_name}</Link>
                    <div className="text-xs text-slate-500">{[c.city, c.country].filter(Boolean).join(', ')} · {c.availability.label}</div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex min-w-36 items-center gap-3">
                      <ScoreBar value={c.score} category={c.category.value} />
                      <span className="w-8 text-right font-bold tabular-nums">{c.score}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3"><CategoryBadge category={c.category} /></td>
                  <td className="px-4 py-3">
                    {c.flags.length > 0
                      ? <span className="cursor-help font-semibold text-amber-600" title={c.flags.join('\n')}>⚑ {c.flags.length}</span>
                      : <span className="text-slate-300">—</span>}
                  </td>
                  <td className="px-4 py-3 text-slate-600">{c.review_status.label}</td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-2 whitespace-nowrap">
                      <Link to={`/dashboard/candidates/${c.id}`} className={ACTION_CLASS}>Fiche</Link>
                      <DownloadButton className={ACTION_CLASS} title={`Télécharger la fiche de ${c.first_name} ${c.last_name} en PDF`}
                        onDownload={() => download(`/candidates/${c.id}/pdf`, { fallbackName: `fiche-${c.id}.pdf` })}>
                        PDF
                      </DownloadButton>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {list.meta.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
              <span className="text-slate-500">{list.meta.from}–{list.meta.to} sur {list.meta.total}</span>
              <div className="flex gap-2">
                <button className="rounded-md border px-3 py-1 disabled:opacity-40" disabled={list.meta.current_page === 1} onClick={() => setFilter('page', String(list.meta.current_page - 1))}>Précédent</button>
                <button className="rounded-md border px-3 py-1 disabled:opacity-40" disabled={list.meta.current_page === list.meta.last_page} onClick={() => setFilter('page', String(list.meta.current_page + 1))}>Suivant</button>
              </div>
            </div>
          )}
        </div>
      )}
      <p className="text-xs text-slate-500">
        Ordre : score, puis qualité des réalisations, puis date de dépôt. Le rang reste le rang global, même filtré.
      </p>
    </div>
  )
}
