import { expect, test } from '@playwright/test'

/**
 * BC-27 SHOWCASE (#6876, E2E v1) — parcours de bout en bout de la vitrine.
 *
 * Deux blocs, comme les autres specs du dépôt :
 *
 * 1. PARCOURS ADMIN (mocké, déterministe) — création 1-clic de la vitrine,
 *    édition d'une section puis publication 1-clic. Adossé aux endpoints réels
 *    `/showcase/*` (aucun mock applicatif : seule la couche réseau est
 *    interceptée) ; requiert `PLAYWRIGHT_AUTH_TOKEN` (pattern
 *    `showcase-editor.spec.js`) et est désactivé sous `BACKEND_LIVE=1`.
 *
 * 2. CONSULTATION PUBLIQUE SANS AUTH (backend réel) — la vitrine publiée est
 *    servie sans aucun compte (API JSON + page SSR `/vitrine/{slug}`), son
 *    contenu est affiché, un brouillon renvoie 404, et le payload public ne
 *    laisse fuiter AUCUN champ interne (`id` numérique, `company_id`,
 *    `showcase_id`, `status`, `preview_token`, `disk`/`path` médias…).
 *    Ces parcours nécessitent un backend réel (le rendu public résout le
 *    tenant par slug, impossible à mocker fidèlement) : ils sont donc gardés
 *    derrière `test.skip(!E2E_BACKEND_URL)`, pattern `tax-slabs.spec.js` /
 *    `social-contributions.spec.js`.
 *
 * Variables d'environnement (bloc 2) :
 *   - `E2E_BACKEND_URL`       : origine (ou base `/api/v1`) de l'API réelle ;
 *   - `E2E_SHOWCASE_SLUG`     : slug d'une vitrine PUBLIÉE à consulter ;
 *   - `E2E_SHOWCASE_DRAFT_SLUG` : slug d'une vitrine en BROUILLON (doit 404).
 *
 * Référence : docs/specifications/SOLUTION_SITE_VITRINE.md (§6 API, §7 SEO,
 * §8 RGPD) et issues #6866/#6871/#6872/#6873/#6875/#6876.
 */

const AUTHENTICATED = Boolean(process.env.PLAYWRIGHT_AUTH_TOKEN)
const LIVE = process.env.BACKEND_LIVE === '1'

// QA 2026-08-15 (#2658) : un parcours public exige un backend réel (résolution
// du tenant par slug) — en CI locale (webServer 127.0.0.1 sans API) il serait
// impossible à exécuter. Gating explicite par E2E_BACKEND_URL.
const hasRealBackend = Boolean(process.env.E2E_BACKEND_URL)
const publishedSlug = process.env.E2E_SHOWCASE_SLUG || ''
const draftSlug = process.env.E2E_SHOWCASE_DRAFT_SLUG || ''

test.describe.configure({ timeout: 120_000 })

// ── 1. Parcours admin (mocké) ───────────────────────────────────────────────

const ADMIN_USER = {
  id: 1,
  name: 'Agent E2E',
  email: 'agent.e2e@leopardo.test',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
}

const HERO_SECTION = {
  id: 11,
  showcase_id: 7,
  type: 'hero',
  content: { heading: 'Acme Industries', subheading: 'Bâtir demain' },
  translations: {},
  sort_order: 10,
  schema_version: 1,
}

const DRAFT_SHOWCASE = {
  id: 7,
  slug: 'acme-industries',
  status: 'draft',
  theme: 'industrie',
  settings: { brand_name: 'Acme Industries' },
  legal: {},
  preview_token: null,
  preview_path: null,
  published_at: null,
  editor_contract: {
    schema_version: 1,
    section_types: ['hero', 'features', 'gallery', 'testimonials', 'contact', 'footer'],
    supported_locales: ['fr', 'en', 'ar', 'tr'],
    default_locale: 'fr',
    rtl_locales: ['ar'],
  },
}

test.describe('Vitrine — création 1-clic, édition puis publication (#6876)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('crée la vitrine en 1 clic, édite une section et publie', async ({ page }) => {
    const calls = { create: 0, publish: 0, sectionPatch: [] }
    let created = false

    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: ADMIN_USER }) }),
    )

    // Aperçu SSR : l'iframe pointe l'origine API réelle — rendu neutre en test.
    await page.route('**/vitrine/**', (route) =>
      route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html lang="fr"><body>aperçu</body></html>' }),
    )

    await page.route(/\/api\/v1\/showcase\/sections\/reorder(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [HERO_SECTION] }) }),
    )

    await page.route(/\/api\/v1\/showcase\/sections\/(\d+)(\?.*)?$/, async (route) => {
      if (route.request().method() === 'PATCH') {
        const body = route.request().postDataJSON()
        calls.sectionPatch.push({ id: Number(route.request().url().match(/sections\/(\d+)/)[1]), body })
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: { ...HERO_SECTION, content: body.content, translations: body.translations || {} } }),
        })
        return
      }
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: HERO_SECTION }) })
    })

    await page.route(/\/api\/v1\/showcase\/sections(\?.*)?$/, async (route) => {
      if (route.request().method() === 'POST') {
        const body = route.request().postDataJSON()
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ data: { id: 12, schema_version: 1, ...body } }),
        })
        return
      }
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [HERO_SECTION] }) })
    })

    await page.route(/\/api\/v1\/showcase\/media(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }),
    )

    await page.route(/\/api\/v1\/showcase\/preview-token(\?.*)?$/, (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { preview_token: 'e2e-preview-token', preview_path: '/public/vitrine/acme-industries?token=e2e-preview-token' } }),
      }),
    )

    await page.route(/\/api\/v1\/showcase\/publish(\?.*)?$/, async (route) => {
      calls.publish += 1
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { ...DRAFT_SHOWCASE, status: 'published', published_at: '2026-09-10T12:00:00+00:00', preview_token: null } }),
      })
    })

    await page.route(/\/api\/v1\/showcase(\?.*)?$/, async (route) => {
      if (route.request().method() === 'POST') {
        calls.create += 1
        created = true
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: DRAFT_SHOWCASE }) })
        return
      }
      if (!created) {
        await route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Not Found' }) })
        return
      }
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: DRAFT_SHOWCASE }) })
    })

    await page.goto('/showcase')

    // (1) Création 1-clic : l'état vide propose la création, POST /showcase.
    await expect(page.getByRole('heading', { name: 'Aucune vitrine pour le moment' })).toBeVisible({ timeout: 15_000 })
    await page.getByRole('button', { name: 'Créer ma vitrine' }).click()
    await expect.poll(() => calls.create).toBe(1)

    // L'éditeur charge la vitrine créée et sa section.
    await expect(page.getByRole('heading', { name: 'Site vitrine' }).first()).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Bandeau principal').first()).toBeVisible()

    // (2) Édition d'une section : sélection + sauvegarde (PATCH réel).
    await page.getByRole('button', { name: /Bandeau principal/ }).first().click()
    await page.locator('#scalar-heading').fill('Acme Industries SARL')
    await page.locator('#scalar-subheading').fill('Bâtir demain, ensemble')
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click()

    await expect.poll(() => calls.sectionPatch.length).toBe(1)
    expect(calls.sectionPatch[0].id).toBe(11)
    expect(calls.sectionPatch[0].body.content.heading).toBe('Acme Industries SARL')
    expect(calls.sectionPatch[0].body.content.subheading).toBe('Bâtir demain, ensemble')

    // (3) Publication 1-clic : POST /showcase/publish + statut mis à jour.
    await page.getByRole('button', { name: 'Publier', exact: true }).click()
    await expect.poll(() => calls.publish).toBe(1)
    await expect(page.getByText('Publié', { exact: true }).first()).toBeVisible()
    await expect(page.getByRole('button', { name: 'Dépublier', exact: true })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Voir le site' })).toBeVisible()
  })
})

// ── 2. Consultation publique sans auth (backend réel) ───────────────────────

/**
 * Racine API : accepte `E2E_BACKEND_URL` = origine seule ou base `/api/v1`.
 */
function backendApiRoot(rawUrl) {
  const trimmed = rawUrl.replace(/\/+$/, '')
  return /\/api\/v\d+$/.test(trimmed) ? trimmed : `${trimmed}/api/v1`
}

/** Origine web (pages SSR `/vitrine/{slug}`, hors `/api/v1`). */
function backendWebOrigin(rawUrl) {
  return new URL(rawUrl).origin
}

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

/** Clés interdites dans la moindre structure du payload public. */
const INTERNAL_KEYS = [
  'company_id',
  'showcase_id',
  'section_id',
  'preview_token',
  'status',
  'disk',
  'path',
  'created_at',
  'updated_at',
  'deleted_at',
  'logo_id',
]

const PUBLIC_TOP_LEVEL_KEYS = [
  'slug',
  'company_name',
  'theme',
  'lang',
  'direction',
  'available_locales',
  'published_at',
  'settings',
  'legal',
  'cookies',
  'meta',
  'media',
  'sections',
]

const PUBLIC_SETTINGS_KEYS = ['colors', 'brand_name', 'tagline', 'og_image', 'logo_url']

/** Toutes les clés rencontrées dans une structure JSON imbriquée. */
function collectKeys(value, keys = new Set()) {
  if (Array.isArray(value)) {
    value.forEach((entry) => collectKeys(entry, keys))
    return keys
  }
  if (value && typeof value === 'object') {
    for (const [key, entry] of Object.entries(value)) {
      keys.add(key)
      collectKeys(entry, keys)
    }
  }
  return keys
}

/** Valeurs exposées sous une clé donnée (`id` média public = uuid, jamais un entier). */
function collectValues(value, key, out = []) {
  if (Array.isArray(value)) {
    value.forEach((entry) => collectValues(entry, key, out))
    return out
  }
  if (value && typeof value === 'object') {
    for (const [entryKey, entry] of Object.entries(value)) {
      if (entryKey === key) out.push(entry)
      collectValues(entry, key, out)
    }
  }
  return out
}

test.describe('Vitrine — consultation publique sans auth (#6873/#6876)', () => {
  test.skip(!hasRealBackend, 'Nécessite un backend réel (E2E_BACKEND_URL)')

  test('une vitrine publiée est consultable sans auth et n’expose aucun champ interne', async ({ page, request }) => {
    test.skip(!publishedSlug, 'Nécessite E2E_SHOWCASE_SLUG (vitrine publiée)')

    const apiRoot = backendApiRoot(process.env.E2E_BACKEND_URL)

    // API publique — aucun en-tête d'authentification.
    const response = await request.get(`${apiRoot}/public/vitrine/${publishedSlug}`)
    expect(response.status()).toBe(200)

    const body = await response.json()
    const data = body.data

    // (a) Contenu réellement affiché : identité + sections non vides.
    expect(data.slug).toBe(publishedSlug)
    expect(typeof data.company_name).toBe('string')
    expect(data.company_name.length).toBeGreaterThan(0)
    expect(Array.isArray(data.sections)).toBe(true)
    expect(data.sections.length).toBeGreaterThan(0)
    expect(JSON.stringify(data.sections).length).toBeGreaterThan(10)
    expect(data.published_at).toBeTruthy()

    // (b) Non-fuite : aucune clé interne, à AUCUN niveau du payload.
    const keys = collectKeys(data)
    for (const internal of INTERNAL_KEYS) {
      expect(keys.has(internal), `champ interne fuité dans le payload public : ${internal}`).toBe(false)
    }

    // `id` n'est toléré que comme identifiant PUBLIC de média (uuid), jamais
    // comme id interne (entier) de vitrine/section.
    for (const id of collectValues(data, 'id')) {
      expect(typeof id).toBe('string')
      expect(id, `id public de média non-uuid : ${String(id)}`).toMatch(UUID_RE)
    }

    // Shape stricte : surface publique en allowlist (toute clé nouvelle doit
    // être un choix explicite, pas une fuite de modèle Eloquent).
    for (const key of Object.keys(data)) {
      expect(PUBLIC_TOP_LEVEL_KEYS, `clé publique inattendue : ${key}`).toContain(key)
    }
    for (const section of data.sections) {
      expect(Object.keys(section).sort()).toEqual(['content', 'schema_version', 'type'])
    }
    for (const key of Object.keys(data.settings || {})) {
      expect(PUBLIC_SETTINGS_KEYS, `clé de settings non allowlistée : ${key}`).toContain(key)
    }

    // (c) Page SSR `/vitrine/{slug}` : consultée dans un vrai navigateur SANS
    // session, elle affiche le contenu de la vitrine publiée.
    await page.goto(`${backendWebOrigin(process.env.E2E_BACKEND_URL)}/vitrine/${publishedSlug}`)
    await expect(page.locator('body')).toContainText(data.company_name)
    const html = await page.content()
    expect(html.toLowerCase()).not.toContain('company_id')
    expect(html.toLowerCase()).not.toContain('preview_token')
  })

  test('un brouillon (et un slug inconnu) renvoie 404, jamais son contenu', async ({ request }) => {
    const apiRoot = backendApiRoot(process.env.E2E_BACKEND_URL)
    const webOrigin = backendWebOrigin(process.env.E2E_BACKEND_URL)

    // Slug inconnu : 404 déterministe, sans auth.
    const unknown = await request.get(`${apiRoot}/public/vitrine/e2e-unknown-${Date.now()}`)
    expect(unknown.status()).toBe(404)
    expect(unknown.headers()['x-robots-tag']).toContain('noindex')

    test.skip(!draftSlug, 'Nécessite E2E_SHOWCASE_DRAFT_SLUG (vitrine brouillon)')

    // Brouillon : la ressource publique doit être indiscernable d'un slug inconnu.
    const draftApi = await request.get(`${apiRoot}/public/vitrine/${draftSlug}`)
    expect(draftApi.status()).toBe(404)
    expect(draftApi.headers()['x-robots-tag']).toContain('noindex')
    const draftBody = await draftApi.text()
    expect(draftBody.toLowerCase()).not.toContain('company_id')
    expect(draftBody.toLowerCase()).not.toContain(draftSlug.toLowerCase())

    // Et la page SSR brouillon renvoie elle aussi 404 (non indexable).
    const draftPage = await request.get(`${webOrigin}/vitrine/${draftSlug}`)
    expect(draftPage.status()).toBe(404)
    expect(draftPage.headers()['x-robots-tag']).toContain('noindex')
  })
})
