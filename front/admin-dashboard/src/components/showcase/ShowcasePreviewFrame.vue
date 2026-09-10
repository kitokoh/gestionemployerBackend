<template>
  <div class="card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 px-4 py-3 dark:border-slate-800/60">
      <h2 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
        <EyeIcon class="h-4 w-4" />
        {{ t('showcase.preview.title', 'Aperçu') }}
      </h2>
      <div class="flex items-center gap-2">
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:bg-slate-800 dark:text-slate-400">
          {{ isDraft ? t('showcase.preview.noindex', 'Brouillon — noindex') : t('showcase.preview.published', 'Publié') }}
        </span>
        <button
          type="button"
          class="btn-secondary py-1.5"
          :disabled="!url"
          @click="$emit('refresh')"
        >
          <ArrowPathIcon class="mr-1 h-4 w-4" />
          {{ t('showcase.preview.refresh', 'Rafraîchir') }}
        </button>
        <a
          v-if="url"
          :href="url"
          target="_blank"
          rel="noopener"
          class="btn-secondary py-1.5"
        >
          {{ t('showcase.preview.open', 'Ouvrir') }}
        </a>
      </div>
    </div>

    <iframe
      v-if="url"
      :src="url"
      :title="t('showcase.preview.title', 'Aperçu')"
      class="h-[38rem] w-full border-0 bg-white"
      loading="lazy"
    ></iframe>
    <p v-else class="p-6 text-sm text-slate-500 dark:text-slate-400">
      {{ t('showcase.preview.unavailable', "Aperçu indisponible : enregistrez la vitrine pour générer le lien.") }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import { ArrowPathIcon, EyeIcon } from '@heroicons/vue/24/outline'

/**
 * Aperçu de la vitrine : iframe sur le VRAI endpoint de rendu SSR
 * (`/vitrine/{slug}?token=…&lang=…`, #6871/#6873/#6874) — aucun HTML
 * reconstruit côté front (spike #6869, décision O2).
 */
const props = defineProps({
  url: { type: String, default: '' },
  status: { type: String, default: 'draft' },
})

defineEmits(['refresh'])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const isDraft = computed(() => props.status !== 'published')
</script>
