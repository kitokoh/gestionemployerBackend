import { expect, test } from '@playwright/test'

/**
 * BC-27 SHOWCASE (#6870/#6874) — éditeur de sections de la vitrine.
 *
 * Parcours minimal (mocks API déterministes, pattern des specs authentifiées) :
 *   - l'entrée de menu « Site vitrine » ouvre la route `/showcase` ;
 *   - l'éditeur liste les sections réelles renvoyées par `GET /showcase/sections`
 *     et propose les types exposés par `editor_contract` ;
 *   - l'ajout d'une section ouvre le panneau d'édition du type et
 *     `POST /showcase/sections` reçoit le contenu saisi (aucune donnée
 *     fabriquée) ;
 *   - le sélecteur de langue du contenu (#6874) expose fr/en/ar/tr.
 */

const AUTHENTICATED = Boolean(process.env.PLAYWRIGHT_AUTH_TOKEN)
const LIVE = process.env.BACKEND_LIVE === '1'

test.describe.configure({ timeout: 120_000 })

const ADMIN_USER = {
  id: 1,
  name: 'Agent E2E',
  email: 'agent.e2e@leopardo.test',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
}

const SHOWCASE = {
  data: {
    id: 7,
    slug: 'acme-industries',
    status: 'draft',
    theme: 'industrie',
    settings: { brand_name: 'Acme Industries' },
    legal: {},
    preview_token: 'e2e-preview-token',
    preview_path: '/public/vitrine/acme-industries?token=e2e-preview-token',
    published_at: null,
    editor_contract: {
      schema_version: 1,
      section_types: ['hero', 'features', 'gallery', 'testimonials', 'contact', 'footer'],
      supported_locales: ['fr', 'en', 'ar', 'tr'],
      default_locale: 'fr',
      rtl_locales: ['ar'],
    },
  },
}

const SECTIONS = {
  data: [
    {
      id: 11,
      showcase_id: 7,
      type: 'hero',
      content: { heading: 'Acme Industries' },
      translations: {},
      sort_order: 10,
      schema_version: 1,
    },
  ],
}

test.describe('Vitrine — éditeur de sections (#6870/#6874)', () => {
  test.skip(!AUTHENTICATED, 'Skipped: requiert PLAYWRIGHT_AUTH_TOKEN (tests authentifiés)')
  test.skip(LIVE, 'Skipped: BACKEND_LIVE=1 — tests mock désactivés')

  test('ouvre l’éditeur, liste les sections et crée une section', async ({ page }) => {
    const posted = []

    await page.addInitScript((token) => {
      sessionStorage.setItem('admin_token', token)
    }, process.env.PLAYWRIGHT_AUTH_TOKEN)

    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: ADMIN_USER }) }),
    )

    await page.route(/\/api\/v1\/showcase\/sections\/reorder(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(SECTIONS) }),
    )
    await page.route(/\/api\/v1\/showcase\/sections(\?.*)?$/, async (route) => {
      if (route.request().method() === 'POST') {
        const body = route.request().postDataJSON()
        posted.push(body)
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ data: { id: 12, schema_version: 1, ...body } }),
        })
        return
      }
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(SECTIONS) })
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
    await page.route(/\/api\/v1\/showcase(\?.*)?$/, async (route) => {
      if (route.request().method() === 'GET') {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(SHOWCASE) })
        return
      }
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(SHOWCASE) })
    })

    await page.goto('/showcase')

    await expect(page.getByRole('heading', { name: 'Site vitrine' }).first()).toBeVisible({ timeout: 15_000 })

    // Section existante listée + sélectionnée (panneau d'édition visible).
    await expect(page.getByText('Bandeau principal').first()).toBeVisible()

    // Sélecteur de langue du contenu (#6874) : 4 locales.
    for (const label of ['Français', 'Anglais', 'Arabe', 'Turc']) {
      await expect(page.getByRole('button', { name: label })).toBeVisible()
    }

    // Ajout d'une section « Atouts » puis saisie du titre requis et sauvegarde.
    await page.getByText('Ajouter une section').click()
    await page.getByRole('button', { name: 'Atouts' }).click()
    await page.locator('#scalar-title').fill('Nos atouts')
    await page.locator('#row-0-title').fill('Qualité')
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click()

    await expect.poll(() => posted.length).toBeGreaterThan(0)
    expect(posted[0].type).toBe('features')
    expect(posted[0].content.title).toBe('Nos atouts')
    expect(posted[0].content.items[0].title).toBe('Qualité')
  })
})
