<template>
  <div class="space-y-2">
    <div class="flex flex-wrap items-center gap-3">
      <img
        v-if="previewUrl"
        :src="previewUrl"
        :alt="t('showcase.media.preview', 'Aperçu du média')"
        class="h-12 w-12 rounded-lg border border-slate-200 object-cover dark:border-slate-700"
      />
      <label class="btn-secondary cursor-pointer py-2">
        <input
          ref="fileInput"
          type="file"
          class="sr-only"
          :accept="accept"
          :disabled="busy"
          @change="onFileChange"
        />
        {{ busy ? t('showcase.media.uploading', 'Envoi…') : t('showcase.media.upload', 'Choisir un fichier') }}
      </label>
      <button
        v-if="selectedId"
        type="button"
        class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400"
        @click="clear"
      >
        {{ t('showcase.media.remove', 'Retirer') }}
      </button>
    </div>

    <select
      v-if="hasMediaOptions"
      class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
      :value="selectedId"
      :aria-label="t('showcase.media.library', 'Médias de la vitrine')"
      @change="selectMedia($event.target.value)"
    >
      <option value="">{{ t('showcase.media.choose', '— Médiathèque —') }}</option>
      <option v-for="item in mediaOptions" :key="item.id" :value="item.id">
        {{ item.original_name || item.id }}
      </option>
    </select>

    <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { showcaseMediaUrl, uploadShowcaseMedia } from '@/services/showcase'

/**
 * Sélecteur / upload de média de vitrine (BC-27 SHOWCASE, #6872).
 *
 * Consomme les endpoints réels `POST /showcase/media` (upload) et la liste
 * `GET /showcase/media` fournie par la vue parente (médiathèque). La valeur
 * liée est l'`uuid` public du média — jamais un chemin.
 */
const props = defineProps({
  media: { type: Array, default: () => [] },
  slug: { type: String, default: '' },
  kind: { type: String, default: 'image' },
  sectionId: { type: Number, default: null },
})

const emit = defineEmits(['uploaded', 'error'])

/** `v-model` (defineModel) : uuid du média sélectionné. */
const selectedId = defineModel({ type: String, default: '' })

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const fileInput = ref(null)
const busy = ref(false)
const error = ref('')

const IMAGE_EXTENSIONS = ['.png', '.jpg', '.jpeg', '.webp']
const LOGO_EXTENSIONS = [...IMAGE_EXTENSIONS, '.svg']

const accept = computed(() =>
  (props.kind === 'logo' ? LOGO_EXTENSIONS : IMAGE_EXTENSIONS).join(','),
)

const mediaOptions = computed(() =>
  (props.media || []).filter((item) => item.kind === props.kind || item.kind === 'image'),
)

const hasMediaOptions = computed(() => mediaOptions.value.length > 0)

const previewUrl = computed(() => {
  if (!selectedId.value) return ''
  const known = (props.media || []).find((item) => item.id === selectedId.value)
  if (known?.url) return known.url
  return showcaseMediaUrl(props.slug, selectedId.value)
})

function selectMedia(value) {
  selectedId.value = value
}

async function onFileChange(event) {
  const file = event?.target?.files?.[0]
  if (!file) return

  busy.value = true
  error.value = ''

  try {
    const response = await uploadShowcaseMedia(file, {
      kind: props.kind,
      sectionId: props.sectionId,
    })
    const payload = response?.data?.data ?? response?.data ?? {}
    const uploaded = payload?.id ? payload : payload?.data ?? {}

    if (uploaded?.id) {
      emit('uploaded', uploaded)
      selectedId.value = uploaded.id
    } else {
      error.value = t('showcase.media.uploadError', "L'envoi du fichier a échoué.")
    }
  } catch (err) {
    error.value = err?.response?.data?.message || t('showcase.media.uploadError', "L'envoi du fichier a échoué.")
    emit('error', error.value)
  } finally {
    busy.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function clear() {
  selectedId.value = ''
}
</script>
