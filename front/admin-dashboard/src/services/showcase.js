import api from '@/services/api'

/**
 * Client API de la vitrine entreprise (BC-27 SHOWCASE).
 *
 * Tous les appels passent par l'instance axios partagée (`/api/v1`) et
 * consomment les endpoints réels du module `Showcase` — aucun mock :
 *   - vitrine     : `GET/POST/PATCH /showcase`, `POST /showcase/publish|unpublish`,
 *                   `POST /showcase/preview-token`, `PATCH /showcase/settings` ;
 *   - sections    : `GET/POST /showcase/sections`, `POST /showcase/sections/reorder`,
 *                   `PATCH/DELETE /showcase/sections/{id}` (#6866) ;
 *   - médias      : `GET/POST /showcase/media`, `DELETE /showcase/media/{id}` (#6872).
 *
 * Référence : docs/specifications/SOLUTION_SITE_VITRINE.md (§6 API) et spike
 * docs/architecture/SHOWCASE_EDITOR_SPIKE_2026-09-08.md (décision O2).
 */

/** Extrait la charge utile data d'une reponse Laravel ({data: {...}}). */
export function showcaseItem(response) {
  const payload = response?.data ?? response ?? {}
  return payload.data ?? payload
}

/** Extrait la liste `data` d'une enveloppe Laravel `{data: [...]}`. */
export function showcaseList(response) {
  const payload = response?.data ?? response ?? {}
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  if (payload.data && Array.isArray(payload.data.data)) return payload.data.data
  return []
}

// ── Vitrine ─────────────────────────────────────────────────────────────────

/** GET /showcase — vitrine du tenant (404 si absente). */
export function getShowcase(options = {}) {
  return api.get('/showcase', { _skipToast: true, _skipAuthRedirect: true, ...options })
}

/** POST /showcase — création 1-clic (idempotent : 200 si déjà créée). */
export function createShowcase() {
  return api.post('/showcase')
}

/** PATCH /showcase — thème et/ou réglages/légal. */
export function updateShowcase(payload) {
  return api.patch('/showcase', payload)
}

/** PATCH /showcase/settings — variables de marque + bloc légal (allowlist). */
export function updateShowcaseSettings(payload) {
  return api.patch('/showcase/settings', payload)
}

/** POST /showcase/publish — publication 1-clic (#6871). */
export function publishShowcase() {
  return api.post('/showcase/publish')
}

/** POST /showcase/unpublish — retour en brouillon (#6871). */
export function unpublishShowcase() {
  return api.post('/showcase/unpublish')
}

/** POST /showcase/preview-token — (re)génère le jeton d'aperçu privé. */
export function rotateShowcasePreviewToken() {
  return api.post('/showcase/preview-token')
}

// ── Sections ────────────────────────────────────────────────────────────────

/** GET /showcase/sections — liste ordonnée des sections. */
export function listShowcaseSections() {
  return api.get('/showcase/sections')
}

/** POST /showcase/sections — ajout d'une section. */
export function createShowcaseSection(payload) {
  return api.post('/showcase/sections', payload)
}

/** PATCH /showcase/sections/{id} — contenu / traductions d'une section. */
export function updateShowcaseSection(id, payload) {
  return api.patch(`/showcase/sections/${id}`, payload)
}

/** DELETE /showcase/sections/{id}. */
export function deleteShowcaseSection(id) {
  return api.delete(`/showcase/sections/${id}`)
}

/** POST /showcase/sections/reorder — `ids` = ordre cible complet. */
export function reorderShowcaseSections(ids) {
  return api.post('/showcase/sections/reorder', { ids })
}

// ── Médias (#6872) ──────────────────────────────────────────────────────────

/** GET /showcase/media — médias de la vitrine (logo + images). */
export function listShowcaseMedia(params = {}) {
  return api.get('/showcase/media', { params, _skipToast: true })
}

/**
 * POST /showcase/media — upload d'un logo ou d'une image de section.
 * `kind` ∈ 'logo' | 'image' ; `sectionId` optionnel (rattachement informatif).
 */
export function uploadShowcaseMedia(file, { kind = 'image', sectionId = null } = {}) {
  const form = new FormData()
  form.append('kind', kind)
  form.append('file', file)
  if (sectionId) form.append('section_id', String(sectionId))
  return api.post('/showcase/media', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

/** DELETE /showcase/media/{id} — `id` = uuid public du média. */
export function deleteShowcaseMedia(id) {
  return api.delete(`/showcase/media/${id}`)
}

// ── Rendu public (aperçu éditeur) ───────────────────────────────────────────

/** Origine de l'API (sans `/api/v1`) — sert les pages SSR `/vitrine/{slug}`. */
export function showcaseApiOrigin() {
  const base = import.meta.env.VITE_API_URL || 'https://gestionemployerbackend.onrender.com/api/v1'
  try {
    return new URL(base).origin
  } catch {
    return ''
  }
}

/**
 * URL du vrai endpoint de rendu SSR privé (#6871/#6873) — brouillon servi via
 * `?token=` (noindex), langue via `?lang=` (#6874). Aucun HTML fabriqué côté
 * front : l'aperçu est un iframe sur cette URL.
 */
export function showcasePreviewUrl(slug, { token = null, locale = null } = {}) {
  if (!slug) return ''
  const params = new URLSearchParams()
  if (token) params.set('token', token)
  if (locale) params.set('lang', locale)
  const query = params.toString()
  return `${showcaseApiOrigin()}/vitrine/${encodeURIComponent(slug)}${query ? `?${query}` : ''}`
}

/** URL publique de service pour un média (logo ou image) de la vitrine. */
export function showcaseMediaUrl(slug, uuid) {
  if (!slug || !uuid) return ''
  return `${showcaseApiOrigin()}/api/v1/public/vitrine/${encodeURIComponent(slug)}/media/${encodeURIComponent(uuid)}`
}
