import { BrowserRouter, Link, NavLink, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider, RequireAuth, useAuth } from './auth'
import ApplyPage from './pages/ApplyPage'
import CandidatePage from './pages/CandidatePage'
import DashboardPage from './pages/DashboardPage'
import LoginPage from './pages/LoginPage'

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Header />
        <main className="mx-auto max-w-6xl px-4 py-8">
          <Routes>
            <Route path="/" element={<ApplyPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/dashboard" element={<RequireAuth><DashboardPage /></RequireAuth>} />
            <Route path="/dashboard/candidates/:id" element={<RequireAuth><CandidatePage /></RequireAuth>} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </main>
      </AuthProvider>
    </BrowserRouter>
  )
}

function Header() {
  const { isAuthenticated, logout } = useAuth()
  const navClass = ({ isActive }) => `text-sm font-medium ${isActive ? 'text-brand-600' : 'text-slate-600 hover:text-slate-900'}`

  return (
    <header className="border-b border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
        <Link to="/" className="text-lg font-extrabold tracking-tight">
          Talent<span className="text-brand-600">Engine</span>
          <span className="ml-2 hidden text-xs font-medium text-slate-400 sm:inline">SKULLVI</span>
        </Link>
        <nav className="flex flex-wrap items-center gap-x-4 gap-y-1 sm:gap-x-5">
          <NavLink to="/" end className={navClass}>Candidater</NavLink>
          <NavLink to="/dashboard" className={navClass}>Espace responsable</NavLink>
          {isAuthenticated && <button onClick={logout} className="text-sm font-medium text-slate-500 hover:text-slate-900">Déconnexion</button>}
        </nav>
      </div>
    </header>
  )
}
