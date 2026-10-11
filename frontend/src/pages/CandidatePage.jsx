import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, download } from '../api'
import { Alert, buttonClass, Card, CategoryBadge, DownloadButton, Field, inputClass, ScoreBar, Spinner } from '../components/ui'

const LEVEL_DOTS = { debutant: 1, intermediaire: 2, avance: 3 }

export default function CandidatePage() {
  const { id } = useParams()
  const [candidate, setCandidate] = useState(null)
  const [options, setOptions] = useState(null)
  const [error, setError] = useState(null)
  const [review, setReview] = useState({ review_status: '', reviewer_note: '' })
  const [saveState, setSaveState] = useState(null) // null | saving | saved | message d'erreur

  useEffect(() => {
    Promise.all([api(`/candidates/${id}`), api('/options')])
      .then(([{ data }, opts]) => {
        setCandidate(data)
        setOptions(opts)
        setReview({ review_status: data.review_status.value, reviewer_note: data.reviewer_note ?? '' })
      })
      .catch((e) => setError(e.message))
  }, [id])

  const saveReview = async (e) => {
    e.preventDefault()
    setSaveState('saving')
    try {
      const { data } = await api(`/candidates/${id}`, { method: 'PATCH', body: review })
      setCandidate(data)
      setSaveState('saved')
    } catch (err) {
      setSaveState(err.message)
    }
  }

  if (error) return <div className="space-y-4"><BackLink /><Alert>{error}</Alert></div>
  if (!candidate) return <Spinner />

  const c = candidate
  const links = [['LinkedIn', c.linkedin_url], ['GitHub', c.github_url], ['Portfolio', c.portfolio_url]].filter(([, url]) => url)

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <BackLink />
        <DownloadButton className={buttonClass.primary} onDownload={() => download(`/candidates/${id}/pdf`, { fallbackName: `fiche-${id}.pdf` })}>
          ⬇ Télécharger la fiche PDF
        </DownloadButton>
      </div>

      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{c.first_name} {c.last_name}</h1>
          <p className="text-slate-500">{[c.city, c.country].filter(Boolean).join(', ')} · rang {c.rank} · reçue le {new Date(c.created_at).toLocaleDateString('fr-FR')}</p>
        </div>
        <div className="text-right">
          <div className="text-4xl font-bold tabular-nums">{c.score}<span className="text-lg font-medium text-slate-400">/100</span></div>
          <CategoryBadge category={c.category} />
          <div className="mt-1 text-xs text-slate-500">{c.category.action}</div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-5">
        <Card className="lg:col-span-3">
          <h2 className="mb-4 text-lg font-semibold">Détail du score</h2>
          <ul className="space-y-4">
            {c.score_details.map((d) => (
              <li key={d.criterion}>
                <div className="flex items-center justify-between text-sm">
                  <span className="font-medium">{d.label}</span>
                  <span className="font-bold tabular-nums">{d.points}<span className="font-normal text-slate-400">/{d.max_points}</span></span>
                </div>
                <div className="my-1.5"><ScoreBar value={d.points} max={d.max_points} /></div>
                <p className="text-xs text-slate-500">{d.explanation}</p>
              </li>
            ))}
          </ul>
          <div className="mt-4 flex justify-between border-t border-slate-200 pt-3 font-bold">
            <span>Total</span><span className="tabular-nums">{c.score}/100</span>
          </div>
          <p className="mt-2 text-xs text-slate-400">Règles de scoring v{c.scoring_version}</p>

          {c.flags.length > 0 && (
            <div className="mt-5">
              <Alert tone="warning">
                <p className="mb-1 font-semibold">Points d'attention</p>
                <ul className="list-disc space-y-1 pl-5">{c.flags.map((f) => <li key={f}>{f}</li>)}</ul>
              </Alert>
            </div>
          )}
        </Card>

        <div className="space-y-6 lg:col-span-2">
          <Card>
            <h2 className="mb-4 text-lg font-semibold">Revue</h2>
            <form onSubmit={saveReview} className="space-y-4">
              <Field label="Statut">
                <select className={inputClass} value={review.review_status} onChange={(e) => { setReview({ ...review, review_status: e.target.value }); setSaveState(null) }}>
                  {options.review_statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
              </Field>
              <Field label="Note interne">
                <textarea className={inputClass} rows={3} maxLength={2000} value={review.reviewer_note} onChange={(e) => { setReview({ ...review, reviewer_note: e.target.value }); setSaveState(null) }} />
              </Field>
              <div className="flex items-center gap-3">
                <button className={buttonClass.primary} disabled={saveState === 'saving'}>{saveState === 'saving' ? 'Enregistrement…' : 'Enregistrer'}</button>
                {saveState === 'saved' && <span className="text-sm text-emerald-600">✓ Enregistré</span>}
                {saveState && !['saving', 'saved'].includes(saveState) && <span className="text-sm text-rose-600">{saveState}</span>}
              </div>
            </form>
          </Card>

          <Card>
            <h2 className="mb-3 text-lg font-semibold">Compétences</h2>
            <ul className="space-y-2">
              {c.skills.map((s) => (
                <li key={s.id} className="flex items-center justify-between text-sm">
                  <span className={s.is_sought ? 'font-medium' : 'text-slate-500'}>
                    {s.name}{!s.is_sought && <span className="ml-1 text-xs">(hors critères)</span>}
                  </span>
                  <span className="flex items-center gap-2 text-xs text-slate-500">
                    {s.level.label}
                    <span className="flex gap-0.5" aria-hidden>
                      {[1, 2, 3].map((n) => <span key={n} className={`size-2 rounded-full ${n <= LEVEL_DOTS[s.level.value] ? 'bg-brand-600' : 'bg-slate-200'}`} />)}
                    </span>
                  </span>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <h2 className="mb-3 text-lg font-semibold">Profil</h2>
          <dl className="grid grid-cols-[7rem_1fr] gap-x-4 gap-y-2 text-sm sm:grid-cols-[9rem_1fr]">
            <Info label="Email"><a className="text-brand-600 hover:underline" href={`mailto:${c.email}`}>{c.email}</a></Info>
            <Info label="Téléphone">{c.phone}</Info>
            <Info label="Formation">{c.education_level.label} · {c.education_field.label}</Info>
            <Info label="Établissement">{[c.institution, c.graduation_year].filter(Boolean).join(', ')}</Info>
            <Info label="Expérience">{c.experience_years} an(s) · {c.experience_type.label}</Info>
            <Info label="Disponibilité">{c.availability.label}{c.available_from && ` · dès le ${new Date(c.available_from).toLocaleDateString('fr-FR')}`}</Info>
            <Info label="Liens">
              {links.length ? links.map(([label, url]) => (
                <a key={label} href={url} target="_blank" rel="noopener noreferrer" className="mr-3 text-brand-600 hover:underline">{label} ↗</a>
              )) : null}
            </Info>
          </dl>
        </Card>
        <Card>
          <h2 className="mb-3 text-lg font-semibold">Projets réalisés</h2>
          <p className="text-sm whitespace-pre-line text-slate-700">{c.projects_description || <span className="text-slate-400">Non renseigné.</span>}</p>
          <h2 className="mt-5 mb-3 text-lg font-semibold">Motivation</h2>
          <p className="text-sm whitespace-pre-line text-slate-700">{c.motivation || <span className="text-slate-400">Non renseignée.</span>}</p>
        </Card>
      </div>
    </div>
  )
}

function Info({ label, children }) {
  return (
    <>
      <dt className="text-slate-500">{label}</dt>
      <dd className="min-w-0 wrap-anywhere">{children || <span className="text-slate-400">—</span>}</dd>
    </>
  )
}

function BackLink() {
  return <Link to="/dashboard" className="text-sm font-medium text-brand-600 hover:underline">← Retour au classement</Link>
}
