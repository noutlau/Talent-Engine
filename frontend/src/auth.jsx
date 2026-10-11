import { createContext, useContext, useEffect, useState } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { api, tokenStore } from './api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [token, setToken] = useState(tokenStore.get())

  useEffect(() => {
    const onExpired = () => setToken(null)
    window.addEventListener('auth:expired', onExpired)
    return () => window.removeEventListener('auth:expired', onExpired)
  }, [])

  const login = async (email, password) => {
    const { token } = await api('/login', { method: 'POST', body: { email, password } })
    tokenStore.set(token)
    setToken(token)
  }

  const logout = async () => {
    await api('/logout', { method: 'POST' }).catch(() => {})
    tokenStore.set(null)
    setToken(null)
  }

  return <AuthContext.Provider value={{ isAuthenticated: Boolean(token), login, logout }}>{children}</AuthContext.Provider>
}

export const useAuth = () => useContext(AuthContext)

export function RequireAuth({ children }) {
  const { isAuthenticated } = useAuth()
  const location = useLocation()
  return isAuthenticated ? children : <Navigate to="/login" replace state={{ from: location.pathname }} />
}
