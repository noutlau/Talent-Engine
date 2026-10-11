const TOKEN_KEY = 'talent-engine-token'

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors // erreurs de validation Laravel : { champ: [messages] }
  }
}

export const tokenStore = {
  get: () => {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set: (token) => {
    try {
      if (token) localStorage.setItem(TOKEN_KEY, token)
      else localStorage.removeItem(TOKEN_KEY)
    } catch {
      // stockage indisponible (navigation privée) : la session ne survivra pas au rechargement
    }
  },
}

/**
 * Télécharge un fichier protégé (CSV, PDF). Un simple lien <a href> ne peut pas envoyer
 * le jeton d'authentification : on récupère donc le fichier, puis on déclenche l'enregistrement.
 */
export async function download(path, { query, fallbackName = 'export' } = {}) {
  const url = new URL(`/api${path}`, window.location.origin)
  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) url.searchParams.set(key, value)
  })

  let response
  try {
    response = await fetch(url, { headers: { Authorization: `Bearer ${tokenStore.get()}` } })
  } catch {
    throw new ApiError(0, 'Impossible de joindre le serveur.')
  }
  if (!response.ok) {
    if (response.status === 401) window.dispatchEvent(new Event('auth:expired'))
    throw new ApiError(response.status, response.status === 404 ? 'Élément introuvable.' : 'Le téléchargement a échoué. Réessayez.')
  }

  const filename = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1] ?? fallbackName
  const link = document.createElement('a')
  link.href = URL.createObjectURL(await response.blob())
  link.download = filename
  link.click()
  setTimeout(() => URL.revokeObjectURL(link.href), 1000)
}

/** Appel à l'API Laravel. Lève une ApiError avec un message lisible en cas d'échec. */
export async function api(path, { method = 'GET', body, query } = {}) {
  const url = new URL(`/api${path}`, window.location.origin)
  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) url.searchParams.set(key, value)
  })

  const token = tokenStore.get()
  let response
  try {
    response = await fetch(url, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    })
  } catch {
    throw new ApiError(0, 'Impossible de joindre le serveur. Vérifiez que l\'API Laravel est démarrée.')
  }

  const data = await response.json().catch(() => ({}))
  if (response.ok) return data

  if (response.status === 401) {
    tokenStore.set(null)
    window.dispatchEvent(new Event('auth:expired'))
  }
  const messages = {
    401: 'Session expirée, reconnectez-vous.',
    404: 'Élément introuvable.',
    422: 'Merci de corriger les champs signalés.',
    429: 'Trop de tentatives. Patientez une minute avant de réessayer.',
  }
  throw new ApiError(response.status, messages[response.status] ?? 'Une erreur est survenue. Réessayez.', data.errors)
}
