<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ t('showcase.title', 'Site vitrine') }}
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ t('showcase.subtitle', "Composez la page publique de votre entreprise par sections, puis publiez en 1 clic.") }}
        </p>
      </div>

      <div v-if="showcase && !gate" class="flex flex-wrap items-center gap-2">
        <StatusBadge :status="showcase.status" :map="statusMap" />
        <button
          v-if="showcase.status === 'published'"
          type="button"
          class="btn-secondary"
          :disabled="publishing"
          @click="togglePublish"
        >
          {{ publishing ? t('showcase.publishing', 'Traitement…') : t('showcase.unpublish', 'Dépublier') }}
        </button>
        <button
          v-else
          type="button"
          class="btn-primary"
          :disabled="publishing"
          @click="togglePublish"
        >
          {{ publishing ? t('showcase.publishing', 'Traitement…') : t('showcase.publish', 'Publier') }}
        </button>
      </div>
    </div>

    <p
      v-if="publishError"
      class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-400"
      role="alert"
    >
      {{ publishError }}
    </p>

    <div v-if="loading" class="card p-10 text-center text-sm text-slate-500 dark:text-slate-400">
      {{ t('showcase.loading', 'Chargement de la vitrine…') }}
    </div>

    <div v-else-if="gate" class="card p-8 text-center">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
        {{ gateTitle }}
      </h2>
      <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ gateMessage }}</p>
      <button type="button" class="btn-secondary mt-4" @click="init">
        {{ t('showcase.retry', 'Réessayer') }}
      </button>
    </div>

    <div v-else-if="!showcase" class="card p-8 text-center">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
        {{ t('showcase.empty.title', 'Aucune vitrine pour le moment') }}
      </h2>
      <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
        {{ t('showcase.empty.body', 'Créez la vitrine de votre entreprise : elle est préparée en brouillon, rien n’est publié avant votre validation.') }}
      </p>
      <button type="button" class="btn-primary mt-4" :disabled="creating" @click="createSite">
        {{ creating ? t('showcase.creating', 'Création…') : t('showcase.empty.create', 'Créer ma vitrine') }}
      </button>
    </div>

    <template v-else>
      <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ t('showcase.editor.language', 'Langue du contenu') }}
          </span>
          <button
            v-for="locale in locales"
            :key="locale"
            type="button"
            class="rounded-full border px-3 py-1 text-sm font-medium transition-colors"
            :class="locale === activeLocale
              ? 'border-brand-500 bg-brand-500 text-white'
              : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'"
            @click="setActiveLocale(locale)"
          >
            {{ localeLabel(locale) }}
          </button>
          <span
            v-if="isRtlActive"
            class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
          >
            {{ t('showcase.editor.rtl', 'RTL') }}
          </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="showcase.status !== 'published'"
            type="button"
            class="btn-secondary py-2"
            @click="refreshPreviewToken"
          >
            {{ t('showcase.editor.refreshToken', "Régénérer le lien d'aperçu") }}
          </button>
          <a
            v-if="showcase.status === 'published'"
            :href="previewUrl"
            target="_blank"
            rel="noopener"
            class="btn-secondary py-2"
          >
            {{ t('showcase.editor.openSite', 'Voir le site') }}
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-4">
          <ShowcaseSectionList
            :sections="sections"
            :selected-id="selectedId"
            :busy="savingSection"
            :available-types="availableTypes"
            @select="selectSection"
            @add="addSection"
            @duplicate="duplicateSection"
            @remove="removeSection"
            @reorder="reorderSections"
          />

          <ShowcaseSettingsPanel
            :showcase="showcase"
            :media="media"
            :busy="savingMeta"
            :error="metaError"
            @save="saveSettings"
            @uploaded="onMediaUploaded"
          />
        </div>

        <div class="space-y-6 xl:col-span-8">
          <div v-if="editorType" class="card p-5">
            <ShowcaseSectionEditor
              :key="editorKey"
              :type="editorType"
              :content="editorContent"
              :translations="editorTranslations"
              :locale="activeLocale"
              :section-id="editorSectionId"
              :slug="showcase.slug"
              :media="media"
              :busy="savingSection"
              :error="sectionError"
              @save="saveSection"
              @cancel="cancelEdit"
              @uploaded="onMediaUploaded"
            />
          </div>

          <div v-else class="card p-8 text-center text-sm text-slate-500 dark:text-slate-400">
            {{ t('showcase.editor.selectPrompt', 'Sélectionnez une section ou ajoutez-en une.') }}
          </div>

          <ShowcasePreviewFrame
            :key="previewKey"
            :url="previewUrl"
            :status="showcase.status"
            @refresh="refreshPreview"
          />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ShowcaseSectionList from '@/components/showcase/ShowcaseSectionList.vue'
import ShowcaseSectionEditor from '@/components/showcase/ShowcaseSectionEditor.vue'
import ShowcaseSettingsPanel from '@/components/showcase/ShowcaseSettingsPanel.vue'
import ShowcasePreviewFrame from '@/components/showcase/ShowcasePreviewFrame.vue'
import {
  SHOWCASE_DEFAULT_LOCALE,
  SHOWCASE_FALLBACK_TYPES,
  SHOWCASE_LOCALES,
  SHOWCASE_RTL_LOCALES,
  emptyContentFor,
  isKnownShowcaseType,
} from '@/constants/showcaseSections'
import {
  createShowcase,
  createShowcaseSection,
  deleteShowcaseSection,
  getShowcase,
  listShowcaseMedia,
  listShowcaseSections,
  publishShowcase,
  reorderShowcaseSections,
  rotateShowcasePreviewToken,
  showcaseItem,
  showcaseList,
  showcasePreviewUrl,
  unpublishShowcase,
  updateShowcase,
  updateShowcaseSection,
  updateShowcaseSettings,
} from '@/services/showcase'

/**
 * Éditeur de sections de la vitrine (BC-27 SHOWCASE, #6870) — éditeur maison
 * (décision spike #6869, O2 : pas de GrapeJS ni de lib de drag & drop).
 *
 * Liste des sections (ajout/édition inline/duplication/suppression/
 * réordonnancement HTML5), panneau d'édition par type, sélecteur de langue
 * du contenu (#6874), upload logo/images (#6872), publication 1-clic (#6871)
 * et aperçu iframe sur le vrai endpoint SSR privé (#6873). Toutes les données
 * proviennent des endpoints réels `/showcase/*` — aucune donnée fabriquée.
 */
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const loading = ref(true)
const gate = ref('')
const loadError = ref('')
const creating = ref(false)

const showcase = ref(null)
const sections = ref([])
const media = ref([])

const activeLocale = ref(
  SHOWCASE_LOCALES.includes(localeStore.current) ? localeStore.current : SHOWCASE_DEFAULT_LOCALE,
)

const selectedId = ref(null)
const draftType = ref('')
const draftContent = ref({})
const draftTranslations = ref({})

const savingSection = ref(false)
const sectionError = ref('')
const savingMeta = ref(false)
const metaError = ref('')
const publishing = ref(false)
const publishError = ref('')
const previewNonce = ref(0)

const locales = SHOWCASE_LOCALES
const isRtlActive = computed(() => SHOWCASE_RTL_LOCALES.includes(activeLocale.value))

const statusMap = {
  draft: { label: t('showcase.status.draft', 'Brouillon'), color: 'gray' },
  published: { label: t('showcase.status.published', 'Publié'), color: 'green' },
}

const contract = computed(() => {
  const value = showcase.value?.editor_contract
  return value && typeof value === 'object' ? value : {}
})

const availableTypes = computed(() => {
  const declared = Array.isArray(contract.value.section_types) ? contract.value.section_types : []
  const known = declared.filter((type) => isKnownShowcaseType(type))
  return known.length > 0 ? known : SHOWCASE_FALLBACK_TYPES
})

const activeSection = computed(() =>
  sections.value.find((section) => section.id === selectedId.value) || null,
)

const editorType = computed(() => (draftType.value ? draftType.value : activeSection.value?.type || ''))
const editorSectionId = computed(() => (draftType.value ? null : activeSection.value?.id ?? null))
const editorContent = computed(() =>
  draftType.value ? draftContent.value : activeSection.value?.content || {},
)
const editorTranslations = computed(() =>
  draftType.value ? draftTranslations.value : activeSection.value?.translations || {},
)
const editorKey = computed(() =>
  `${draftType.value || editorSectionId.value || 'none'}-${activeLocale.value}`,
)

const previewUrl = computed(() => {
  if (!showcase.value?.slug) return ''
  const token = showcase.value.status === 'published' ? null : showcase.value.preview_token || null
  return showcasePreviewUrl(showcase.value.slug, { token, locale: activeLocale.value })
})

const previewKey = computed(() => `${previewUrl.value}#${previewNonce.value}`)

const gateTitle = computed(() => {
  if (gate.value === 'feature') return t('showcase.gate.featureTitle', 'Module vitrine inactif')
  if (gate.value === 'tenant') return t('showcase.gate.tenantTitle', 'Contexte entreprise requis')
  return t('showcase.gate.errorTitle', 'Chargement impossible')
})

const gateMessage = computed(() => {
  if (gate.value === 'feature') {
    return t('showcase.gate.featureBody', "Le module vitrine n'est pas activé pour cette entreprise. Contactez un administrateur plateforme.")
  }
  if (gate.value === 'tenant') {
    return t('showcase.gate.tenantBody', 'La vitrine se gère avec une session entreprise (responsable ou RH).')
  }
  return loadError.value || t('showcase.gate.errorBody', "Une erreur est survenue en interrogeant l'API vitrine.")
})

function localeLabel(locale) {
  return t(`showcase.locale.${locale}`, locale)
}

function setActiveLocale(locale) {
  activeLocale.value = locale
}

async function loadSections() {
  const response = await listShowcaseSections()
  sections.value = showcaseList(response)
}

async function loadMedia() {
  const response = await listShowcaseMedia()
  media.value = showcaseList(response)
}

async function init() {
  loading.value = true
  gate.value = ''
  loadError.value = ''
  selectedId.value = null
  draftType.value = ''
  publishError.value = ''

  try {
    const response = await getShowcase()
    showcase.value = showcaseItem(response)
    await Promise.all([loadSections(), loadMedia()])

    if (showcase.value.status !== 'published' && !showcase.value.preview_token) {
      // Première visite d'un brouillon : génère le jeton d'aperçu privé
      // (#6871) pour que l'iframe de prévisualisation soit servie.
      await refreshPreviewToken()
    }

    if (sections.value.length > 0) {
      selectSection(sections.value[0])
    }
  } catch (err) {
    const status = err?.response?.status
    const code = err?.response?.data?.error
    showcase.value = null

    if (status === 404) {
      // Aucune vitrine : l'état vide propose la création 1-clic.
    } else if (status === 403 && code === 'FEATURE_NOT_ENABLED') {
      gate.value = 'feature'
    } else if (status === 401) {
      gate.value = 'tenant'
    } else {
      gate.value = 'error'
      loadError.value = err?.response?.data?.message || String(err)
    }
  } finally {
    loading.value = false
  }
}

async function createSite() {
  creating.value = true
  try {
    await createShowcase()
    await init()
  } catch (err) {
    loadError.value = err?.response?.data?.message || String(err)
    gate.value = 'error'
  } finally {
    creating.value = false
  }
}

function selectSection(section) {
  selectedId.value = section.id
  draftType.value = ''
  draftContent.value = {}
  draftTranslations.value = {}
  sectionError.value = ''
}

function addSection(type) {
  draftType.value = type
  draftContent.value = emptyContentFor(type)
  draftTranslations.value = {}
  selectedId.value = null
  sectionError.value = ''
}

function duplicateSection(section) {
  draftType.value = section.type
  draftContent.value = JSON.parse(JSON.stringify(section.content || {}))
  draftTranslations.value = JSON.parse(JSON.stringify(section.translations || {}))
  selectedId.value = null
  sectionError.value = ''
}

function cancelEdit() {
  draftType.value = ''
  draftContent.value = {}
  draftTranslations.value = {}
  sectionError.value = ''
}

async function saveSection(payload) {
  savingSection.value = true
  sectionError.value = ''

  try {
    if (draftType.value) {
      const response = await createShowcaseSection({
        type: draftType.value,
        content: payload.content,
        translations: payload.translations,
      })
      const created = showcaseItem(response)
      draftType.value = ''
      draftContent.value = {}
      draftTranslations.value = {}
      await loadSections()
      if (created?.id) selectedId.value = created.id
    } else if (selectedId.value) {
      await updateShowcaseSection(selectedId.value, {
        content: payload.content,
        translations: payload.translations,
      })
      await loadSections()
    }
    refreshPreview()
  } catch (err) {
    sectionError.value = err?.response?.data?.message || String(err)
  } finally {
    savingSection.value = false
  }
}

async function removeSection(section) {
  if (!window.confirm(t('showcase.editor.confirmDelete', 'Supprimer cette section ? Cette action est irréversible.'))) {
    return
  }

  try {
    await deleteShowcaseSection(section.id)
    if (selectedId.value === section.id) {
      selectedId.value = null
    }
    await loadSections()
    refreshPreview()
  } catch (err) {
    sectionError.value = err?.response?.data?.message || String(err)
  }
}

async function reorderSections(ids) {
  try {
    const response = await reorderShowcaseSections(ids)
    sections.value = showcaseList(response)
  } catch (err) {
    sectionError.value = err?.response?.data?.message || String(err)
    await loadSections().catch(() => {})
  }
}

async function saveSettings(payload) {
  savingMeta.value = true
  metaError.value = ''

  try {
    if (payload.theme && payload.theme !== showcase.value.theme) {
      await updateShowcase({ theme: payload.theme })
    }
    await updateShowcaseSettings({ settings: payload.settings, legal: payload.legal })

    const response = await getShowcase()
    showcase.value = showcaseItem(response)
    refreshPreview()
  } catch (err) {
    metaError.value = err?.response?.data?.message || String(err)
  } finally {
    savingMeta.value = false
  }
}

async function togglePublish() {
  publishing.value = true
  publishError.value = ''

  try {
    const response = showcase.value.status === 'published'
      ? await unpublishShowcase()
      : await publishShowcase()
    showcase.value = showcaseItem(response)
    refreshPreview()
  } catch (err) {
    publishError.value = err?.response?.data?.message || String(err)
  } finally {
    publishing.value = false
  }
}

async function refreshPreviewToken() {
  try {
    const response = await rotateShowcasePreviewToken()
    const payload = response?.data?.data ?? response?.data ?? {}
    showcase.value = { ...showcase.value, preview_token: payload.preview_token, preview_path: payload.preview_path }
    refreshPreview()
  } catch (err) {
    publishError.value = err?.response?.data?.message || String(err)
  }
}

function refreshPreview() {
  previewNonce.value += 1
}

function onMediaUploaded(item) {
  if (!item?.id) return
  if (!media.value.some((entry) => entry.id === item.id)) {
    media.value = [...media.value, item]
  }
}

onMounted(init)
</script>
