<template>
  <form class="card space-y-5 p-5" novalidate @submit.prevent="save">
    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
      {{ t('showcase.settings.title', 'Thème et identité') }}
    </h2>

    <FormField :id="ids.theme" :label="t('showcase.settings.theme', 'Thème')">
      <select
        id="showcase-theme"
        :value="form.theme"
        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
        @change="setTheme($event.target.value)"
      >
        <option v-for="theme in themes" :key="theme" :value="theme">
          {{ t(`showcase.theme.${theme}`, theme) }}
        </option>
      </select>
    </FormField>

    <FormField :id="ids.brand" :label="t('showcase.settings.brandName', 'Nom de marque')">
      <input
        id="showcase-brand"
        v-model="form.brandName"
        type="text"
        maxlength="120"
        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
      />
    </FormField>

    <FormField :id="ids.tagline" :label="t('showcase.settings.tagline', 'Slogan')">
      <input
        id="showcase-tagline"
        v-model="form.tagline"
        type="text"
        maxlength="280"
        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
      />
    </FormField>

    <FormField
      :id="ids.logo"
      :label="t('showcase.settings.logo', 'Logo')"
      :hint="t('showcase.settings.logoHint', 'PNG, JPEG, WebP ou SVG — 2 Mo max.')"
    >
      <ShowcaseMediaPicker
        v-model="form.logoId"
        :media="media"
        :slug="slug"
        kind="logo"
        @uploaded="$emit('uploaded', $event)"
      />
    </FormField>

    <fieldset class="space-y-3">
      <legend class="text-sm font-medium text-slate-700 dark:text-slate-200">
        {{ t('showcase.settings.colors', 'Couleurs') }}
      </legend>
      <div class="grid grid-cols-2 gap-3">
        <label
          v-for="key in colorKeys"
          :key="key"
          class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300"
        >
          <input
            type="color"
            class="h-8 w-10 rounded border border-slate-300 dark:border-slate-700"
            :value="colorValue(key) || '#000000'"
            :aria-label="t(`showcase.color.${key}`, key)"
            @input="setColor(key, $event.target.value)"
          />
          {{ t(`showcase.color.${key}`, key) }}
        </label>
      </div>
    </fieldset>

    <FormField :id="ids.legalNotice" :label="t('showcase.settings.legalNotice', 'Mentions légales')">
      <textarea
        id="showcase-legal-notice"
        v-model="form.legalNotice"
        rows="3"
        maxlength="2000"
        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
      ></textarea>
    </FormField>

    <FormField :id="ids.legalPrivacy" :label="t('showcase.settings.legalPrivacy', 'Politique de confidentialité')">
      <textarea
        id="showcase-legal-privacy"
        v-model="form.legalPrivacy"
        rows="3"
        maxlength="4000"
        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
      ></textarea>
    </FormField>

    <FormField :id="ids.legalEmail" :label="t('showcase.settings.legalEmail', 'E-mail de contact (mentions)')">
      <input
        id="showcase-legal-email"
        v-model="form.legalEmail"
        type="email"
        maxlength="190"
        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
      />
    </FormField>

    <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-400" role="alert">
      {{ error }}
    </p>

    <div class="flex justify-end">
      <button type="submit" class="btn-primary" :disabled="busy">
        {{ busy ? t('showcase.editor.saving', 'Enregistrement…') : t('showcase.settings.save', 'Enregistrer les réglages') }}
      </button>
    </div>
  </form>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import FormField from '@/components/common/FormField.vue'
import ShowcaseMediaPicker from '@/components/showcase/ShowcaseMediaPicker.vue'
import { SHOWCASE_COLOR_KEYS, SHOWCASE_THEMES } from '@/constants/showcaseSections'

/**
 * Réglages de la vitrine (BC-27 SHOWCASE, #6868/#6875) : thème v1, identité
 * de marque (nom, slogan, logo), palette et bloc légal. Les valeurs sont
 * persistées via `PATCH /showcase` (thème) et `PATCH /showcase/settings`
 * (allowlist serveur) — aucun mock.
 */
const props = defineProps({
  showcase: { type: Object, default: () => ({}) },
  media: { type: Array, default: () => [] },
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['save', 'uploaded'])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const themes = SHOWCASE_THEMES
const colorKeys = SHOWCASE_COLOR_KEYS

/** Identifiants des champs (label FormField ↔ input). */
const ids = {
  theme: 'showcase-theme',
  brand: 'showcase-brand',
  tagline: 'showcase-tagline',
  logo: 'showcase-logo',
  legalNotice: 'showcase-legal-notice',
  legalPrivacy: 'showcase-legal-privacy',
  legalEmail: 'showcase-legal-email',
}

const form = reactive({
  theme: '',
  brandName: '',
  tagline: '',
  logoId: '',
  colors: {},
  legalNotice: '',
  legalPrivacy: '',
  legalEmail: '',
})

function reset() {
  const settings = props.showcase?.settings && typeof props.showcase.settings === 'object' ? props.showcase.settings : {}
  const legal = props.showcase?.legal && typeof props.showcase.legal === 'object' ? props.showcase.legal : {}
  const colors = settings.colors && typeof settings.colors === 'object' ? settings.colors : {}

  form.theme = props.showcase?.theme || ''
  form.brandName = settings.brand_name || ''
  form.tagline = settings.tagline || ''
  form.logoId = settings.logo_id || ''
  form.colors = { ...colors }
  form.legalNotice = legal.notice || ''
  form.legalPrivacy = legal.privacy || ''
  form.legalEmail = legal.contact_email || ''
}

watch(() => props.showcase, reset, { immediate: true })

function colorValue(key) {
  const value = form.colors[key]
  return typeof value === 'string' ? value : ''
}

function setTheme(value) {
  form.theme = value
}

function setColor(key, value) {
  form.colors = { ...form.colors, [key]: value }
}

function save() {
  const settings = {
    brand_name: form.brandName,
    tagline: form.tagline,
    logo_id: form.logoId,
  }

  const colors = {}
  for (const key of colorKeys) {
    const value = colorValue(key)
    if (value) colors[key] = value
  }
  if (Object.keys(colors).length > 0) settings.colors = colors

  emit('save', {
    theme: form.theme,
    settings,
    legal: {
      notice: form.legalNotice,
      privacy: form.legalPrivacy,
      contact_email: form.legalEmail,
    },
  })
}
</script>
