import { useEffect, useState } from 'react'
import { api, ApiError } from '../api'
import { Alert, buttonClass, Card, Field, inputClass, Spinner } from '../components/ui'

const EMPTY_FORM = {
  first_name: '', last_name: '', email: '', phone: '', city: '', country: '',
  linkedin_url: '', github_url: '', portfolio_url: '',
  education_level: '', education_field: '', institution: '', graduation_year: '',
  experience_years: '', experience_type: '', projects_description: '', motivation: '',
  availability: '', available_from: '', consent: false, website: '',
}

export default function ApplyPage() {
  const [options, setOptions] = useState(null)
  const [skills, setSkills] = useState([])
  const [loadError, setLoadError] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [selectedSkills, setSelectedSkills] = useState({}) // { skillId: niveau }
  const [errors, setErrors] = useState({})
  const [status, setStatus] = useState('idle') // idle | sending | sent
  const [message, setMessage] = useState(null)

  useEffect(() => {
    Promise.all([api('/options'), api('/skills')])
      .then(([opts, skillList]) => {
        setOptions(opts)
        setSkills(skillList)
      })
      .catch((e) => setLoadError(e.message))
  }, [])

  const set = (field) => (e) => setForm({ ...form, [field]: e.target.type === 'checkbox' ? e.target.checked : e.target.value })
  const error = (field) => errors[field]?.[0]
  const input = (field, props = {}) => (
    <input className={inputClass} value={form[field]} onChange={set(field)} aria-invalid={Boolean(error(field))} {...props} />
  )
  const select = (field, choices) => (
    <select className={inputClass} value={form[field]} onChange={set(field)} aria-invalid={Boolean(error(field))}>
      <option value="">Choisir…</option>
      {choices.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  )

  const toggleSkill = (id) => {
    const next = { ...selectedSkills }
    if (next[id]) delete next[id]
    else next[id] = 'intermediaire' // niveau par défaut, modifiable
    setSelectedSkills(next)
  }

  const submit = async (e) => {
    e.preventDefault()
    if (status === 'sending') return // évite le double envoi
    setStatus('sending')
    setErrors({})
    setMessage(null)
    try {
      const { message } = await api('/candidates', {
        method: 'POST',
        body: { ...form, skills: Object.entries(selectedSkills).map(([id, level]) => ({ id: Number(id), level })) },
      })
      setStatus('sent')
      setMessage(message)
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (err) {
      setStatus('idle')
      setMessage(err.message)
      if (err instanceof ApiError) setErrors(err.errors ?? {})
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  if (loadError) return <Alert>{loadError}</Alert>
  if (!options) return <Spinner />

  if (status === 'sent') {
    return (
      <Card className="mx-auto max-w-xl text-center">
        <div className="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-emerald-100 text-2xl">✓</div>
        <h1 className="text-xl font-bold">{message}</h1>
        <p className="mt-2 text-slate-600">Un membre de l'équipe SKULLVI étudiera votre profil. Nous reviendrons vers vous par e-mail.</p>
        <button className={`${buttonClass.secondary} mt-6`} onClick={() => { setForm(EMPTY_FORM); setSelectedSkills({}); setStatus('idle'); setMessage(null) }}>
          Déposer une autre candidature
        </button>
      </Card>
    )
  }

  const skillErrors = Object.entries(errors).filter(([key]) => key.startsWith('skills')).flatMap(([, msgs]) => msgs)
  const errorCount = Object.keys(errors).length

  return (
    <div className="mx-auto max-w-3xl">
      <header className="mb-6">
        <h1 className="text-2xl font-bold tracking-tight">Candidater aux programmes SKULLVI</h1>
        <p className="mt-1 text-slate-600">
          Quelques minutes suffisent. Les champs marqués <span className="text-rose-500">*</span> sont obligatoires.
          Chaque candidature est relue par un membre de l'équipe.
        </p>
      </header>

      {message && <div className="mb-4"><Alert>{message}{errorCount > 0 && ` (${errorCount} champ${errorCount > 1 ? 's' : ''})`}</Alert></div>}

      <form onSubmit={submit} noValidate className="space-y-6">
        {/* Champ piège invisible : seul un robot le remplit */}
        <input type="text" name="website" value={form.website} onChange={set('website')} tabIndex={-1} autoComplete="off" className="hidden" aria-hidden />

        <Card>
          <h2 className="mb-4 text-lg font-semibold">1. Informations personnelles</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Prénom" required error={error('first_name')}>{input('first_name', { autoComplete: 'given-name' })}</Field>
            <Field label="Nom" required error={error('last_name')}>{input('last_name', { autoComplete: 'family-name' })}</Field>
            <Field label="Email" required error={error('email')}>{input('email', { type: 'email', autoComplete: 'email' })}</Field>
            <Field label="Téléphone" error={error('phone')}>{input('phone', { type: 'tel', placeholder: '+237 6 90 11 22 33' })}</Field>
            <Field label="Ville" error={error('city')}>{input('city')}</Field>
            <Field label="Pays" required error={error('country')}>{input('country')}</Field>
            <Field label="LinkedIn" error={error('linkedin_url')}>{input('linkedin_url', { type: 'url', placeholder: 'https://www.linkedin.com/in/…' })}</Field>
            <Field label="GitHub ou GitLab" error={error('github_url')}>{input('github_url', { type: 'url', placeholder: 'https://github.com/…' })}</Field>
            <Field label="Portfolio" error={error('portfolio_url')} className="sm:col-span-2">{input('portfolio_url', { type: 'url', placeholder: 'https://…' })}</Field>
          </div>
        </Card>

        <Card>
          <h2 className="mb-4 text-lg font-semibold">2. Formation</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Niveau d'études" required error={error('education_level')}>{select('education_level', options.education_levels)}</Field>
            <Field label="Domaine de formation" required error={error('education_field')}>{select('education_field', options.education_fields)}</Field>
            <Field label="Établissement" error={error('institution')}>{input('institution')}</Field>
            <Field label="Année d'obtention" error={error('graduation_year')}>{input('graduation_year', { type: 'number', min: 1970, max: 2032 })}</Field>
          </div>
        </Card>

        <Card>
          <h2 className="text-lg font-semibold">3. Compétences <span className="text-rose-500">*</span></h2>
          <p className="mb-4 text-sm text-slate-500">Sélectionnez vos compétences puis indiquez votre niveau, honnêtement : il sera vérifié en entretien.</p>
          <div className="flex flex-wrap gap-2">
            {skills.map((skill) => {
              const selected = Boolean(selectedSkills[skill.id])
              return (
                <button type="button" key={skill.id} onClick={() => toggleSkill(skill.id)} aria-pressed={selected}
                  className={`rounded-full border px-3 py-1.5 text-sm font-medium transition ${selected ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-300 bg-white text-slate-700 hover:border-brand-500'}`}>
                  {selected ? '✓ ' : ''}{skill.name}
                </button>
              )
            })}
          </div>
          {Object.keys(selectedSkills).length > 0 && (
            <div className="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
              {skills.filter((s) => selectedSkills[s.id]).map((skill) => (
                <div key={skill.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                  <span className="text-sm font-medium">{skill.name}</span>
                  <div className="flex gap-1" role="radiogroup" aria-label={`Niveau en ${skill.name}`}>
                    {options.skill_levels.map((level) => (
                      <button type="button" key={level.value} role="radio" aria-checked={selectedSkills[skill.id] === level.value}
                        onClick={() => setSelectedSkills({ ...selectedSkills, [skill.id]: level.value })}
                        className={`rounded-md px-2.5 py-1 text-xs font-semibold ${selectedSkills[skill.id] === level.value ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}>
                        {level.label}
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
          {skillErrors.length > 0 && <p className="mt-2 text-xs font-medium text-rose-600">{[...new Set(skillErrors)].join(' ')}</p>}
        </Card>

        <Card>
          <h2 className="mb-4 text-lg font-semibold">4. Expérience et réalisations</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Années d'expérience" required error={error('experience_years')} hint="Stages, freelance et projets sérieux comptent.">
              {input('experience_years', { type: 'number', min: 0, max: 40, step: 0.5 })}
            </Field>
            <Field label="Type d'expérience principal" required error={error('experience_type')}>{select('experience_type', options.experience_types)}</Field>
            <Field label="Projets réalisés" error={error('projects_description')} className="sm:col-span-2" hint="Le problème, votre rôle, les technologies utilisées. 150 caractères ou plus pour une description complète.">
              <textarea className={inputClass} rows={4} maxLength={2000} value={form.projects_description} onChange={set('projects_description')} aria-invalid={Boolean(error('projects_description'))} />
            </Field>
            <Field label="Motivation" error={error('motivation')} className="sm:col-span-2">
              <textarea className={inputClass} rows={3} maxLength={3000} value={form.motivation} onChange={set('motivation')} aria-invalid={Boolean(error('motivation'))} />
            </Field>
          </div>
        </Card>

        <Card>
          <h2 className="mb-4 text-lg font-semibold">5. Disponibilité</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Disponibilité" required error={error('availability')}>{select('availability', options.availabilities)}</Field>
            <Field label="Disponible à partir du" error={error('available_from')} hint="Laissez vide si vous êtes disponible immédiatement.">{input('available_from', { type: 'date' })}</Field>
          </div>
        </Card>

        <label className="flex items-start gap-3 text-sm text-slate-700">
          <input type="checkbox" className="mt-0.5 size-4 rounded border-slate-300 accent-brand-600" checked={form.consent} onChange={set('consent')} />
          <span>
            J'accepte que SKULLVI traite ces informations dans le seul but d'étudier ma candidature. <span className="text-rose-500">*</span>
            {error('consent') && <span className="block text-xs font-medium text-rose-600">{error('consent')}</span>}
          </span>
        </label>

        <button type="submit" disabled={status === 'sending'} className={`${buttonClass.primary} w-full py-3 sm:w-auto`}>
          {status === 'sending' ? 'Envoi en cours…' : 'Envoyer ma candidature'}
        </button>
      </form>
    </div>
  )
}
