<template>
  <div class="space-y-8 animate-fade-in max-w-5xl">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
          {{ $t('showcase.title') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
          {{ $t('showcase.subtitle') }}
        </p>
      </div>
      <div class="flex items-center gap-3">
        <span
          class="px-3 py-1 rounded-full text-sm font-bold"
          :class="isPublished ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'"
        >
          {{ isPublished ? $t('showcase.published') : $t('showcase.draft') }}
        </span>
        <button
          v-if="showcase"
          type="button"
          class="btn-primary"
          :disabled="busy"
          @click="togglePublish"
        >
          {{ isPublished ? $t('showcase.unpublish') : $t('showcase.publish') }}
        </button>
      </div>
    </div>

    <p v-if="error" class="glass-card p-4 text-rose-600 font-medium">{{ error }}</p>

    <!-- Création 1-clic -->
    <div v-if="!showcase && !loading" class="glass-card p-6">
      <p class="text-slate-600 dark:text-slate-300 mb-4">{{ $t('showcase.empty') }}</p>
      <button type="button" class="btn-primary" :disabled="busy" @click="createShowcase">
        {{ $t('showcase.create') }}
      </button>
    </div>

    <template v-if="showcase">
      <!-- Aperçu -->
      <div class="glass-card p-6 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-64">
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="showcase-preview">
            {{ $t('showcase.preview_link') }}
          </label>
          <input id="showcase-preview" class="form-input w-full" :value="previewUrl" readonly>
        </div>
        <button type="button" class="btn-secondary" :disabled="busy" @click="rotatePreview">
          {{ $t('showcase.generate_preview') }}
        </button>
        <a
          v-if="previewUrl"
          class="text-sm font-bold text-emerald-600 hover:underline"
          :href="previewUrl"
          target="_blank"
          rel="noopener"
        >{{ $t('showcase.open_preview') }}</a>
      </div>

      <!-- Réglages : thème, marque, légal -->
      <div class="glass-card p-6 space-y-4">
        <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ $t('showcase.settings') }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="showcase-theme">
              {{ $t('showcase.theme') }}
            </label>
            <select id="showcase-theme" v-model="form.theme" class="form-input w-full">
              <option v-for="theme in themes" :key="theme" :value="theme">{{ theme }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="showcase-brand">
              {{ $t('showcase.brand_name') }}
            </label>
            <input id="showcase-brand" v-model="form.brandName" class="form-input w-full">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="showcase-tagline">
              {{ $t('showcase.tagline') }}
            </label>
            <input id="showcase-tagline" v-model="form.tagline" class="form-input w-full">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="showcase-notice">
              {{ $t('showcase.legal_notice') }}
            </label>
            <textarea id="showcase-notice" v-model="form.legalNotice" rows="3" class="form-input w-full"></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="showcase-privacy">
              {{ $t('showcase.legal_privacy') }}
            </label>
            <textarea id="showcase-privacy" v-model="form.legalPrivacy" rows="3" class="form-input w-full"></textarea>
          </div>
        </div>
        <button type="button" class="btn-primary" :disabled="busy" @click="saveSettings">
          {{ $t('showcase.save_settings') }}
        </button>
      </div>

      <!-- Sections -->
      <div class="glass-card p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ $t('showcase.sections') }}</h2>
          <div class="flex items-center gap-2">
            <select v-model="newType" class="form-input" aria-label="Section type">
              <option v-for="type in sectionTypes" :key="type" :value="type">{{ type }}</option>
            </select>
            <button type="button" class="btn-primary" :disabled="busy" @click="addSection">
              {{ $t('showcase.add_section') }}
            </button>
          </div>
        </div>

        <p v-if="sections.length === 0" class="text-slate-500">{{ $t('showcase.no_sections') }}</p>

        <ul class="space-y-3">
          <li
            v-for="(section, index) in sections"
            :key="section.id"
            class="rounded-xl border border-slate-200 dark:border-slate-700 p-4"
            :data-testid="`showcase-section-${section.id}`"
          >
            <div class="flex flex-wrap items-center gap-2">
              <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-xs font-bold uppercase">{{ section.type }}</span>
              <div class="flex-1"></div>
              <button type="button" class="btn-secondary" :disabled="busy || index === 0" @click="move(index, -1)">↑</button>
              <button type="button" class="btn-secondary" :disabled="busy || index === sections.length - 1" @click="move(index, 1)">↓</button>
              <button type="button" class="btn-primary" :disabled="busy" @click="saveSection(section)">{{ $t('showcase.save') }}</button>
              <button type="button" class="btn-secondary text-rose-600" :disabled="busy" @click="removeSection(section)">{{ $t('showcase.delete') }}</button>
            </div>
            <textarea v-model="section.editor"
              rows="4"
              class="form-input w-full mt-3 font-mono text-sm"
              :data-testid="`showcase-section-content-${section.id}`"></textarea>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'

const toast = useToast()

const loading = ref(false)
const busy = ref(false)
const error = ref('')

const showcase = ref(null)
const sections = ref([])
const themes = ref(['industrie', 'service', 'commerce'])
const sectionTypes = ref(['hero', 'features', 'gallery', 'testimonials', 'products', 'contact', 'footer'])
const newType = ref('hero')

const form = reactive({
  theme: 'industrie',
  brandName: '',
  tagline: '',
  legalNotice: '',
  legalPrivacy: '',
})

const isPublished = computed(() => showcase.value?.status === 'published')
const previewUrl = computed(() => {
  if (!showcase.value) return ''
  const base = api.defaults?.baseURL || ''
  const path = showcase.value.preview_path || `/public/vitrine/${showcase.value.slug}`
  return path.startsWith('http') ? path : `${base.replace(/\/api\/v1\/?$/, '')}${path}`
})

function applyShowcase(data) {
  showcase.value = data
  form.theme = data.theme || 'industrie'
  form.brandName = data.settings?.brand_name || ''
  form.tagline = data.settings?.tagline || ''
  form.legalNotice = data.legal?.notice || ''
  form.legalPrivacy = data.legal?.privacy || ''
}

function applySections(list) {
  sections.value = (list || []).map((section) => ({
    ...section,
    editor: JSON.stringify(section.content ?? {}, null, 2),
  }))
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/showcase')
    applyShowcase(data.data)
    const sectionsResponse = await api.get('/showcase/sections')
    applySections(sectionsResponse.data.data)
  } catch (e) {
    if (e?.response?.status === 404) {
      showcase.value = null
    } else {
      error.value = e?.response?.data?.message || e.message
    }
  } finally {
    loading.value = false
  }
}

async function createShowcase() {
  busy.value = true
  try {
    const { data } = await api.post('/showcase')
    applyShowcase(data.data)
    toast.success(translate('showcase.created'))
  } catch (e) {
    error.value = e?.response?.data?.message || e.message
  } finally {
    busy.value = false
  }
}

async function saveSettings() {
  busy.value = true
  try {
    const { data } = await api.put('/showcase/settings', {
      theme: form.theme,
      settings: {
        brand_name: form.brandName,
        tagline: form.tagline,
      },
      legal: {
        notice: form.legalNotice,
        privacy: form.legalPrivacy,
      },
    })
    applyShowcase(data.data)
    toast.success(translate('showcase.settings_saved'))
  } catch (e) {
    error.value = e?.response?.data?.message || e.message
  } finally {
    busy.value = false
  }
}

async function togglePublish() {
  busy.value = true
  try {
    const action = isPublished.value ? 'unpublish' : 'publish'
    const { data } = await api.post(`/showcase/${action}`)
    applyShowcase(data.data)
    toast.success(translate(action === 'publish' ? 'showcase.published' : 'showcase.draft'))
  } catch (e) {
    const errors = e?.response?.data?.errors
    error.value = errors?.sections?.[0] || e?.response?.data?.message || e.message
  } finally {
    busy.value = false
  }
}

async function rotatePreview() {
  busy.value = true
  try {
    const { data } = await api.post('/showcase/preview-token')
    showcase.value = { ...showcase.value, preview_path: data.data.preview_path, preview_token: data.data.preview_token }
    toast.success(translate('showcase.preview_generated'))
  } catch (e) {
    error.value = e?.response?.data?.message || e.message
  } finally {
    busy.value = false
  }
}

async function addSection() {
  busy.value = true
  try {
    const { data } = await api.post('/showcase/sections', { type: newType.value, content: {} })
    await load()
    toast.success(translate('showcase.section_saved'))
    return data
  } catch (e) {
    error.value = e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : e.message
  } finally {
    busy.value = false
  }
}

async function saveSection(section) {
  busy.value = true
  try {
    let content
    try {
      content = JSON.parse(section.editor || '{}')
    } catch {
      error.value = translate('showcase.invalid_json')
      return
    }
    await api.patch(`/showcase/sections/${section.id}`, { content })
    toast.success(translate('showcase.section_saved'))
  } catch (e) {
    error.value = e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : e.message
  } finally {
    busy.value = false
  }
}

async function removeSection(section) {
  busy.value = true
  try {
    await api.delete(`/showcase/sections/${section.id}`)
    await load()
    toast.success(translate('showcase.section_deleted'))
  } catch (e) {
    error.value = e?.response?.data?.message || e.message
  } finally {
    busy.value = false
  }
}

async function move(index, delta) {
  const target = index + delta
  if (target < 0 || target >= sections.value.length) return
  const reordered = [...sections.value]
  const [item] = reordered.splice(index, 1)
  reordered.splice(target, 0, item)
  sections.value = reordered
  busy.value = true
  try {
    await api.post('/showcase/sections/reorder', { ids: reordered.map((s) => s.id) })
  } catch (e) {
    error.value = e?.response?.data?.message || e.message
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>
