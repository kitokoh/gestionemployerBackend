<template>
  <form class="space-y-5" novalidate @submit.prevent="save">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">
        {{ t('showcase.editor.title', 'Édition de la section') }}
        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">— {{ typeLabel }}</span>
      </h3>
      <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
        <LanguageIcon class="h-3.5 w-3.5" />
        {{ localeLabel }}
      </span>
    </div>

    <p
      v-if="!isDefaultLocale"
      class="rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-800 dark:bg-brand-950/40 dark:text-brand-300"
    >
      {{ t('showcase.editor.translationHint', 'Traduisez les champs : ceux laissés vides reprennent automatiquement le contenu de référence.') }}
    </p>
    <p
      v-else-if="isRtl"
      class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
    >
      {{ t('showcase.editor.rtlHint', "L'arabe s'affiche de droite à gauche sur la vitrine publiée.") }}
    </p>

    <div v-for="field in scalarFields" :key="field.key" class="space-y-1">
      <FormField
        :id="`scalar-${field.key}`"
        :label="fieldLabel(field)"
        :required="isRequired(field)"
        :error="fieldError(field.key)"
        :hint="fieldHint(field)"
      >
        <ShowcaseFieldInput
          :id="`scalar-${field.key}`"
          :field="field"
          :value="fieldValue(field.key)"
          :required="isRequired(field)"
          :slug="slug"
          :media="media"
          :section-id="sectionId"
          @update:value="setFieldValue(field.key, $event)"
          @uploaded="$emit('uploaded', $event)"
        />
      </FormField>
    </div>

    <fieldset v-if="collection" class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
      <legend class="px-1 text-sm font-semibold text-slate-700 dark:text-slate-200">
        {{ collectionLabel }}
      </legend>

      <div
        v-for="(row, index) in collectionRows"
        :key="index"
        class="space-y-3 rounded-lg bg-slate-50/60 p-3 dark:bg-slate-800/40"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ t('showcase.editor.itemNumber', 'Élément') }} {{ index + 1 }}
          </span>
          <button
            v-if="isDefaultLocale && collectionRows.length > 1"
            type="button"
            class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400"
            @click="removeRow(index)"
          >
            {{ t('showcase.editor.removeItem', 'Supprimer') }}
          </button>
        </div>

        <div v-for="field in collectionFields" :key="field.key" class="space-y-1">
          <FormField
            :id="`row-${index}-${field.key}`"
            :label="fieldLabel(field)"
            :required="isRequired(field)"
            :error="rowError(index, field.key)"
          >
            <ShowcaseFieldInput
              :id="`row-${index}-${field.key}`"
              :field="field"
              :value="rowValue(index, field.key)"
              :required="isRequired(field)"
              :slug="slug"
              :media="media"
              :section-id="sectionId"
              @update:value="setRowValue(index, field.key, $event)"
              @uploaded="$emit('uploaded', $event)"
            />
          </FormField>
        </div>
      </div>

      <button
        v-if="canAddRow"
        type="button"
        class="btn-secondary py-2"
        @click="addRow"
      >
        {{ t('showcase.editor.addItem', 'Ajouter un élément') }}
      </button>
    </fieldset>

    <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-400" role="alert">
      {{ error }}
    </p>

    <div class="flex justify-end gap-2 pt-1">
      <button type="button" class="btn-secondary" @click="$emit('cancel')">
        {{ t('showcase.editor.cancel', 'Annuler') }}
      </button>
      <button type="submit" class="btn-primary" :disabled="busy">
        {{ busy ? t('showcase.editor.saving', 'Enregistrement…') : t('showcase.editor.save', 'Enregistrer') }}
      </button>
    </div>
  </form>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { LanguageIcon } from '@heroicons/vue/24/outline'
import FormField from '@/components/common/FormField.vue'
import ShowcaseFieldInput from '@/components/showcase/ShowcaseFieldInput.vue'
import {
  SHOWCASE_DEFAULT_LOCALE,
  SHOWCASE_RTL_LOCALES,
  SHOWCASE_SECTION_TYPES,
  emptyCollectionRow,
  pickLocalizableContent,
  resolveLocalizedContent,
} from '@/constants/showcaseSections'
import { showcaseMediaUrl } from '@/services/showcase'

/**
 * Panneau d'édition d'une section de vitrine (BC-27 SHOWCASE, #6870/#6874).
 *
 * - Formulaire par type adossé au contrat de sections (#6866) ;
 * - sélecteur de langue porté par la vue parente : en locale de référence on
 *   édite `content` (tous les champs), en locale traduite on édite une
 *   surcouche `translations[locale]` limitée aux champs localisables ;
 * - upload d'images via l'API médias réelle (#6872).
 */
const props = defineProps({
  type: { type: String, required: true },
  content: { type: Object, default: () => ({}) },
  translations: { type: Object, default: () => ({}) },
  locale: { type: String, default: SHOWCASE_DEFAULT_LOCALE },
  sectionId: { type: Number, default: null },
  slug: { type: String, default: '' },
  media: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['save', 'cancel', 'uploaded'])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const spec = computed(() => SHOWCASE_SECTION_TYPES[props.type] || { fields: [], collection: null })
const scalarFields = computed(() => spec.value.fields || [])
const collection = computed(() => spec.value.collection || null)
const collectionFields = computed(() => collection.value?.fields || [])
const isDefaultLocale = computed(() => props.locale === SHOWCASE_DEFAULT_LOCALE)
const isRtl = computed(() => SHOWCASE_RTL_LOCALES.includes(props.locale))

const form = ref({})
const errors = ref({})

const typeLabel = computed(() => t(`showcase.type.${props.type}`, props.type))
const localeLabel = computed(() => t(`showcase.locale.${props.locale}`, props.locale))
const collectionLabel = computed(() =>
  t(`showcase.collection.${collection.value?.key}`, collection.value?.key || ''),
)

function clone(value) {
  try {
    return JSON.parse(JSON.stringify(value ?? {}))
  } catch {
    return {}
  }
}

function buildForm() {
  const base = props.content && typeof props.content === 'object' ? props.content : {}

  if (isDefaultLocale.value) {
    form.value = clone(base)
  } else {
    const overlay = props.translations ? props.translations[props.locale] : null
    form.value = resolveLocalizedContent(props.type, base, overlay)
  }

  if (collection.value) {
    const key = collection.value.key
    if (!Array.isArray(form.value[key]) || form.value[key].length === 0) {
      form.value[key] = [emptyCollectionRow(props.type)]
    }
  }

  errors.value = {}
}

watch(
  () => [props.type, props.locale, props.sectionId, props.content, props.translations],
  buildForm,
  { immediate: true },
)

const collectionRows = computed(() => {
  const key = collection.value?.key
  const rows = key ? form.value[key] : null
  return Array.isArray(rows) ? rows : []
})

function fieldValue(key) {
  const value = form.value[key]
  return value === undefined || value === null ? '' : String(value)
}

function setFieldValue(key, value) {
  form.value[key] = value
}

function rowValue(index, key) {
  const row = collectionRows.value[index]
  const value = row ? row[key] : ''
  return value === undefined || value === null ? '' : String(value)
}

function setRowValue(index, key, value) {
  const row = collectionRows.value[index]
  if (!row) return
  row[key] = value

  // Galerie : le schéma serveur exige `image_url` ; un média uploadé fournit
  // la vraie URL de service (#6872) pour satisfaire ce champ tout en gardant
  // `image_id` comme référence préférée.
  if (key === 'image_id' && value) {
    const hasImageUrl = collectionFields.value.some((field) => field.key === 'image_url')
    if (hasImageUrl) row.image_url = showcaseMediaUrl(props.slug, value)
  }
}

function isRequired(field) {
  if (!isDefaultLocale.value) return false
  return field.required === true
}

function fieldError(key) {
  return errors.value[`scalar.${key}`] || ''
}

function rowError(index, key) {
  return errors.value[`row.${index}.${key}`] || ''
}

function fieldLabel(field) {
  return t(`showcase.field.${field.key}`, field.key.replace(/_/g, ' '))
}

function fieldHint(field) {
  if (field.widget !== 'media') return ''
  return t('showcase.field.mediaHint', 'PNG, JPEG ou WebP — 5 Mo max (SVG pour le logo).')
}

function canAddRow() {
  if (!isDefaultLocale.value || !collection.value) return false
  const max = collection.value.maxItems || 99
  return collectionRows.value.length < max
}

function addRow() {
  const key = collection.value?.key
  if (!key) return
  form.value[key] = [...collectionRows.value, emptyCollectionRow(props.type)]
}

function removeRow(index) {
  const key = collection.value?.key
  if (!key) return
  const rows = [...collectionRows.value]
  rows.splice(index, 1)
  form.value[key] = rows.length > 0 ? rows : [emptyCollectionRow(props.type)]
}

function validate() {
  const next = {}

  for (const field of scalarFields.value) {
    const value = fieldValue(field.key)

    if (isDefaultLocale.value && field.required && value.trim() === '') {
      next[`scalar.${field.key}`] = t('showcase.editor.required', 'Ce champ est obligatoire.')
      continue
    }

    if (field.maxlength && value.length > field.maxlength) {
      next[`scalar.${field.key}`] = t('showcase.editor.tooLong', 'Texte trop long.')
    }
  }

  if (collection.value) {
    collectionRows.value.forEach((_row, index) => {
      for (const field of collectionFields.value) {
        const value = rowValue(index, field.key)

        if (isDefaultLocale.value && field.required && value.trim() === '') {
          next[`row.${index}.${field.key}`] = t('showcase.editor.required', 'Ce champ est obligatoire.')
          continue
        }

        if (field.maxlength && value.length > field.maxlength) {
          next[`row.${index}.${field.key}`] = t('showcase.editor.tooLong', 'Texte trop long.')
        }
      }
    })
  }

  errors.value = next

  return Object.keys(next).length === 0
}

function save() {
  if (!validate()) return

  if (isDefaultLocale.value) {
    // Contenu de référence : tous les champs (médias/URLs inclus).
    emit('save', {
      content: clone(form.value),
      translations: clone(props.translations || {}),
    })

    return
  }

  // Traduction : uniquement les champs localisables non vides.
  const overlay = pickLocalizableContent(props.type, form.value)
  const nextTranslations = clone(props.translations || {})

  if (Object.keys(overlay).length === 0) {
    delete nextTranslations[props.locale]
  } else {
    nextTranslations[props.locale] = overlay
  }

  emit('save', {
    content: clone(props.content || {}),
    translations: nextTranslations,
  })
}
</script>
