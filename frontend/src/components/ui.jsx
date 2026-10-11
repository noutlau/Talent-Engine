import { useState } from 'react'

export const CATEGORY_STYLES = {
  prioritaire: { badge: 'bg-emerald-100 text-emerald-800 ring-emerald-600/20', bar: 'bg-emerald-500', icon: '⭐' },
  interessant: { badge: 'bg-sky-100 text-sky-800 ring-sky-600/20', bar: 'bg-sky-500', icon: '🟢' },
  a_approfondir: { badge: 'bg-amber-100 text-amber-800 ring-amber-600/20', bar: 'bg-amber-500', icon: '🟡' },
  faible: { badge: 'bg-rose-100 text-rose-800 ring-rose-600/20', bar: 'bg-rose-400', icon: '🔴' },
}

export function CategoryBadge({ category }) {
  const style = CATEGORY_STYLES[category.value]
  return (
    <span className={`inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${style.badge}`}>
      <span aria-hidden>{style.icon}</span>
      {category.label}
    </span>
  )
}

export function ScoreBar({ value, max = 100, category }) {
  const percent = max ? Math.round((100 * value) / max) : 0
  const color = category ? CATEGORY_STYLES[category].bar : 'bg-brand-500'
  return (
    <div className="h-2 w-full overflow-hidden rounded-full bg-slate-200" role="meter" aria-valuenow={value} aria-valuemin={0} aria-valuemax={max}>
      <div className={`h-full rounded-full ${color}`} style={{ width: `${percent}%` }} />
    </div>
  )
}

export function Card({ className = '', children }) {
  return <div className={`rounded-xl border border-slate-200 bg-white p-5 shadow-sm ${className}`}>{children}</div>
}

export function Alert({ tone = 'error', children }) {
  const tones = {
    error: 'border-rose-200 bg-rose-50 text-rose-800',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
  }
  return <div role={tone === 'error' ? 'alert' : 'status'} className={`rounded-lg border px-4 py-3 text-sm ${tones[tone]}`}>{children}</div>
}

export function Spinner({ label = 'Chargement…' }) {
  return (
    <div className="flex items-center justify-center gap-3 py-16 text-slate-500">
      <span className="size-5 animate-spin rounded-full border-2 border-slate-300 border-t-brand-600" />
      {label}
    </div>
  )
}

export const inputClass =
  'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none aria-[invalid=true]:border-rose-400 aria-[invalid=true]:bg-rose-50'

export const buttonClass = {
  primary: 'inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60',
  secondary: 'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-60',
}

/** Bouton de téléchargement : état « en cours » et erreur affichés sur place. */
export function DownloadButton({ onDownload, className = buttonClass.secondary, children, title }) {
  const [state, setState] = useState('idle') // idle | loading | erreur
  const click = async () => {
    setState('loading')
    try {
      await onDownload()
      setState('idle')
    } catch (err) {
      setState(err.message)
    }
  }
  return (
    <span className="inline-flex items-center gap-2">
      <button type="button" onClick={click} disabled={state === 'loading'} className={className} title={title}>
        {state === 'loading' ? 'Préparation…' : children}
      </button>
      {!['idle', 'loading'].includes(state) && <span className="text-xs text-rose-600">{state}</span>}
    </span>
  )
}

/** Champ de formulaire avec libellé, aide et message d'erreur. */
export function Field({ label, required, error, hint, children, className = '' }) {
  return (
    <label className={`block text-sm font-medium text-slate-700 ${className}`}>
      {label} {required && <span className="text-rose-500">*</span>}
      {children}
      {hint && !error && <span className="mt-1 block text-xs font-normal text-slate-500">{hint}</span>}
      {error && <span className="mt-1 block text-xs font-medium text-rose-600">{error}</span>}
    </label>
  )
}
