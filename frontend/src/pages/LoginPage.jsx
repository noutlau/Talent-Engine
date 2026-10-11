import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth'
import { Alert, buttonClass, Card, Field, inputClass } from '../components/ui'

export default function LoginPage() {
  const { isAuthenticated, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [sending, setSending] = useState(false)

  if (isAuthenticated) return <Navigate to="/dashboard" replace />

  const submit = async (e) => {
    e.preventDefault()
    setSending(true)
    setError(null)
    try {
      await login(email, password)
      navigate(location.state?.from ?? '/dashboard', { replace: true })
    } catch (err) {
      setError(err.errors?.email?.[0] ?? err.message)
      setSending(false)
    }
  }

  return (
    <Card className="mx-auto mt-10 max-w-sm">
      <h1 className="text-xl font-bold">Espace responsable</h1>
      <p className="mb-5 text-sm text-slate-500">Accès réservé à l'équipe SKULLVI.</p>
      <form onSubmit={submit} className="space-y-4">
        {error && <Alert>{error}</Alert>}
        <Field label="Email"><input className={inputClass} type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="username" required autoFocus /></Field>
        <Field label="Mot de passe"><input className={inputClass} type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required /></Field>
        <button className={`${buttonClass.primary} w-full`} disabled={sending}>{sending ? 'Connexion…' : 'Se connecter'}</button>
      </form>
      <p className="mt-4 text-xs text-slate-500">Démo : admin@skullvi.test / skullvi-demo</p>
    </Card>
  )
}
