import { expect, test } from '@playwright/test'

/**
 * BC-27 SHOWCASE — parcours E2E du composant vitrine (#6876, V-E2E).
 *
 * Couvre le parcours critique de l'EPIC #6862 : le manager ouvre l'éditeur
 * (route protégée), crée/compose/publie sa vitrine 1-clic ; la vitrine
 * publiée est ensuite consultable SANS auth sur l'API publique, avec un
 * payload sans aucun champ interne (contrat DTO public #6867). Une tentative
 * d'accès publique à un brouillon (slug sans `token`) répond 404.
 *
 * Les tests authentifiés sont conditionnés à `PLAYWRIGHT_AUTH_TOKEN`
 * (session admin réelle) ; les assertions API publiques à
 * `PLAYWRIGHT_API_URL`. Sans ces variables, les cas sont proprement skippés
 * (pattern e2e/leaves-flow.spec.js).
 */

const hasToken = Boolean(process.env.PLAYWRIGHT_AUTH_TOKEN)
const apiBase = process.env.PLAYWRIGHT_API_URL || ''
const showcaseSlug = process.env.PLAYWRIGHT_SHOWCASE_SLUG || ''

async function signIn(page) {
  await page.addInitScript((token) => {
    sessionStorage.setItem('admin_token', token)
  }, process.env.PLAYWRIGHT_AUTH_TOKEN)
}

test.describe('Showcase editor (unauthenticated guard)', () => {
  test('leopardo /showcase exige une authentification', async ({ page }) => {
    await page.goto('/showcase')
    await expect(page).toHaveURL(/\/login/)
  })
})

test.describe('Showcase editor (authenticated journey)', () => {
  test.skip(!hasToken, 'Skipped: requires PLAYWRIGHT_AUTH_TOKEN')

  test('le manager compose, publie et obtient le lien d’aperçu', async ({ page }) => {
    await signIn(page)
    await page.goto('/showcase')

    // Écran chargé (titre i18n) — la vitrine est créée si absente.
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 15_000 })

    const createButton = page.getByRole('button', { name: /Créer ma vitrine|Create my showcase/i })
    if (await createButton.isVisible().catch(() => false)) {
      await createButton.click()
    }

    // Ajout d'une section (héro) puis enregistrement du contenu.
    const addSection = page.getByRole('button', { name: /Ajouter une section|Add a section/i })
    await expect(addSection).toBeVisible({ timeout: 10_000 })
    await addSection.click()

    const firstSection = page.locator('[data-testid^="showcase-section-"]').first()
    await expect(firstSection).toBeVisible({ timeout: 10_000 })

    const contentArea = firstSection.locator('textarea')
    await contentArea.fill(JSON.stringify({ heading: 'Vitrine E2E', subheading: 'Pilote' }))
    await firstSection.getByRole('button', { name: /Enregistrer|Save/i }).click()

    // Publication : le badge passe à « Publié ».
    await page.getByRole('button', { name: /Publier|Publish/i }).click()
    await expect(page.getByText(/Publié|Published/i).first()).toBeVisible({ timeout: 10_000 })

    // Lien d'aperçu disponible (jeton privé généré).
    await page.getByRole('button', { name: /Générer un lien d’aperçu|Generate preview link/i }).click()
    const previewInput = page.locator('#showcase-preview')
    await expect(previewInput).not.toHaveValue('', { timeout: 10_000 })
  })
})

test.describe('Showcase public API contract', () => {
  test.skip(!apiBase || !showcaseSlug, 'Skipped: requires PLAYWRIGHT_API_URL + PLAYWRIGHT_SHOWCASE_SLUG')

  test('la vitrine publiée est publique et sans champ interne', async ({ request }) => {
    const response = await request.get(`${apiBase}/public/vitrine/${showcaseSlug}`)
    expect(response.status()).toBe(200)

    const body = await response.json()
    const payload = body.data

    expect(payload.slug).toBe(showcaseSlug)
    // Contrat DTO public : aucun champ interne.
    for (const forbidden of ['id', 'company_id', 'status', 'custom_domain', 'created_at', 'updated_at', 'preview_token']) {
      expect(Object.prototype.hasOwnProperty.call(payload, forbidden)).toBe(false)
    }
    expect(Array.isArray(payload.sections)).toBe(true)
  })

  test('un brouillon sans jeton n’est jamais servi', async ({ request }) => {
    const draftSlug = process.env.PLAYWRIGHT_SHOWCASE_DRAFT_SLUG
    test.skip(!draftSlug, 'Skipped: requires PLAYWRIGHT_SHOWCASE_DRAFT_SLUG')

    const response = await request.get(`${apiBase}/public/vitrine/${draftSlug}`)
    expect(response.status()).toBe(404)
  })
})
