/**
 * Contrat d'édition des sections de la vitrine (BC-27 SHOWCASE, #6870/#6874).
 *
 * Miroir front du registre serveur
 * `App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry` (#6866) :
 * types v1, champs par type, bornes de longueur et champs LOCALISABLES (les
 * champs non localisables — médias, icônes, e-mails, URLs — sont partagés
 * entre les locales et ne sont jamais dupliqués dans `translations`).
 *
 * La liste des types réellement acceptés par l'API est exposée par
 * `GET /showcase` (`editor_contract.section_types`) : l'éditeur suit le
 * backend quand le type `products` (BC-28 #6891) entre dans le registre. Ce
 * module ne sert que de repli et de métadonnées d'UI (libellés i18n, widgets).
 */

export const SHOWCASE_DEFAULT_LOCALE = 'fr'

export const SHOWCASE_LOCALES = ['fr', 'en', 'ar', 'tr']

export const SHOWCASE_RTL_LOCALES = ['ar']

export const SHOWCASE_STATUS = {
  DRAFT: 'draft',
  PUBLISHED: 'published',
}

/** Thèmes v1 (#6868) — allowlist identique à ShowcaseTheme::v1(). */
export const SHOWCASE_THEMES = ['industrie', 'service', 'commerce']

/** Variables de couleur éditables (#6868) — sous-allowlist du DTO public. */
export const SHOWCASE_COLOR_KEYS = ['primary', 'accent', 'surface', 'on_primary']

const text = (key, maxlength, options = {}) => ({ key, widget: 'text', maxlength, ...options })
const textarea = (key, maxlength, options = {}) => ({ key, widget: 'textarea', maxlength, ...options })
const url = (key, maxlength, options = {}) => ({ key, widget: 'url', maxlength, ...options })
const email = (key, maxlength, options = {}) => ({ key, widget: 'email', maxlength, ...options })
const media = (key, options = {}) => ({ key, widget: 'media', ...options })

/**
 * Spécification d'un type de section.
 * - `fields` : champs scalaires du contenu (racine) ;
 * - `collection` : liste répétable optionnelle (`items` / `links`) et ses champs.
 */
export const SHOWCASE_SECTION_TYPES = {
  hero: {
    fields: [
      text('heading', 120, { required: true }),
      textarea('subheading', 280),
      media('image_id', { localizable: false }),
      url('image_url', 500, { localizable: false }),
      text('cta_label', 40),
      url('cta_url', 500, { localizable: false }),
    ],
    collection: null,
  },
  features: {
    fields: [text('title', 120)],
    collection: {
      key: 'items',
      maxItems: 12,
      fields: [
        text('icon', 40, { localizable: false }),
        text('title', 120, { required: true }),
        textarea('description', 500),
      ],
    },
  },
  gallery: {
    fields: [text('title', 120)],
    collection: {
      key: 'items',
      maxItems: 20,
      fields: [
        media('image_id', { localizable: false }),
        url('image_url', 500, { required: true, localizable: false }),
        text('caption', 200),
      ],
    },
  },
  testimonials: {
    fields: [text('title', 120)],
    collection: {
      key: 'items',
      maxItems: 12,
      fields: [
        textarea('quote', 1000, { required: true }),
        text('author', 120, { required: true }),
        text('role', 120),
      ],
    },
  },
  contact: {
    fields: [
      text('title', 120),
      email('email', 190, { required: true, localizable: false }),
      text('phone', 40),
      textarea('address', 300),
    ],
    collection: null,
  },
  footer: {
    fields: [textarea('text', 500)],
    collection: {
      key: 'links',
      maxItems: 12,
      fields: [
        text('label', 80, { required: true }),
        url('url', 500, { required: true, localizable: false }),
      ],
    },
  },
}

/** Repli si `GET /showcase` n'expose pas encore `editor_contract`. */
export const SHOWCASE_FALLBACK_TYPES = Object.keys(SHOWCASE_SECTION_TYPES)

/** Vrai si le type est connu du contrat front. */
export function isKnownShowcaseType(type) {
  return Object.prototype.hasOwnProperty.call(SHOWCASE_SECTION_TYPES, type)
}

/** Vrai si le champ est localisable (traduisible dans `translations`). */
export function isLocalizableField(field) {
  return field.localizable !== false
}

/**
 * Longueurs maximales d'un type, à plat (`items.title` pour les sous-champs).
 *
 * @returns {Record<string, number>}
 */
export function showcaseMaxLengths(type) {
  const spec = SHOWCASE_SECTION_TYPES[type]
  if (!spec) return {}
  const lengths = {}
  for (const field of spec.fields) {
    if (field.maxlength) lengths[field.key] = field.maxlength
  }
  if (spec.collection) {
    for (const field of spec.collection.fields) {
      if (field.maxlength) lengths[`${spec.collection.key}.${field.key}`] = field.maxlength
    }
  }
  return lengths
}

/** Ligne vide d'une collection (squelette local, jamais persisté tel quel). */
export function emptyCollectionRow(type) {
  const spec = SHOWCASE_SECTION_TYPES[type]
  if (!spec || !spec.collection) return {}
  const row = {}
  for (const field of spec.collection.fields) {
    row[field.key] = ''
  }
  return row
}

/**
 * Squelette de contenu pour une NOUVELLE section. Ce squelette n'est PAS
 * persisté : l'utilisateur remplit les champs (dont les requis) avant que
 * l'éditeur n'appelle `POST /showcase/sections` — aucune donnée fabriquée.
 */
export function emptyContentFor(type) {
  const spec = SHOWCASE_SECTION_TYPES[type]
  if (!spec) return {}
  const content = {}
  for (const field of spec.fields) {
    content[field.key] = ''
  }
  if (spec.collection) {
    content[spec.collection.key] = [emptyCollectionRow(type)]
  }
  return content
}

/**
 * Ne conserve que les champs localisables d'un contenu (surcouche de
 * traduction) : les champs partagés (médias, URLs, e-mails, icônes) restent
 * dans `content` et ne sont jamais dupliqués par locale.
 */
export function pickLocalizableContent(type, content) {
  const spec = SHOWCASE_SECTION_TYPES[type]
  if (!spec || !content || typeof content !== 'object') return {}

  const overlay = {}

  for (const field of spec.fields) {
    if (!isLocalizableField(field)) continue
    const value = content[field.key]
    if (typeof value === 'string' && value.trim() !== '') overlay[field.key] = value
  }

  if (spec.collection) {
    const rows = Array.isArray(content[spec.collection.key]) ? content[spec.collection.key] : []
    const localizedRows = []

    for (const row of rows) {
      const localizedRow = {}
      if (row && typeof row === 'object') {
        for (const field of spec.collection.fields) {
          if (!isLocalizableField(field)) continue
          const value = row[field.key]
          if (typeof value === 'string' && value.trim() !== '') localizedRow[field.key] = value
        }
      }
      localizedRows.push(localizedRow)
    }

    if (localizedRows.some((row) => Object.keys(row).length > 0)) {
      overlay[spec.collection.key] = localizedRows
    }
  }

  return overlay
}

/**
 * Fusion d'une surcouche locale sur un contenu de référence — même sémantique
 * que le serveur (`ShowcaseSectionContentResolver::resolve`) : scalaires
 * traduits, listes fusionnées index par index, repli sur la référence.
 */
export function resolveLocalizedContent(type, content, translation) {
  const base = content && typeof content === 'object' ? content : {}
  if (!translation || typeof translation !== 'object') return { ...base }

  const resolved = { ...base }

  for (const [key, value] of Object.entries(translation)) {
    const baseValue = resolved[key]

    if (Array.isArray(value) && Array.isArray(baseValue)) {
      const merged = baseValue.map((item) => ({ ...(item && typeof item === 'object' ? item : {}) }))
      value.forEach((item, index) => {
        const baseItem = merged[index]
        merged[index] = item && typeof item === 'object' && baseItem
          ? { ...baseItem, ...item }
          : item
      })
      resolved[key] = merged
      continue
    }

    if (value && typeof value === 'object' && !Array.isArray(value) && baseValue && typeof baseValue === 'object') {
      resolved[key] = { ...baseValue, ...value }
      continue
    }

    resolved[key] = value
  }

  return resolved
}
