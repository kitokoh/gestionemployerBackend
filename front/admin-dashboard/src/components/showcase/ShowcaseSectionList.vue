<template>
  <div class="card p-4">
    <div class="flex items-center justify-between">
      <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
        {{ t('showcase.editor.sections', 'Sections de la page') }}
      </h2>
      <span class="text-xs text-slate-400">{{ sections.length }}</span>
    </div>

    <ol class="mt-3 space-y-2" :aria-label="t('showcase.editor.sections', 'Sections de la page')">
      <li
        v-for="(section, index) in orderedSections"
        :key="section.id"
        :draggable="!busy"
        class="group flex items-start gap-2 rounded-xl border px-3 py-2.5 transition-colors"
        :class="section.id === selectedId
          ? 'border-brand-500 bg-brand-50/70 dark:border-brand-500 dark:bg-brand-950/30'
          : 'border-slate-200 bg-white/60 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900/40 dark:hover:bg-slate-800/60'"
        @dragstart="onDragStart(index, $event)"
        @dragover.prevent="onDragOver(index)"
        @drop.prevent="onDrop"
        @dragend="onDragEnd"
      >
        <button
          type="button"
          class="mt-0.5 cursor-grab text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
          :aria-label="t('showcase.editor.dragHandle', 'Réordonner')"
          @click="select(section)"
        >
          <Bars3Icon class="h-4 w-4" />
        </button>

        <button type="button" class="min-w-0 flex-1 text-left" @click="select(section)">
          <span class="flex items-center gap-1.5">
            <component :is="iconFor(section.type)" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
            <span class="text-sm font-semibold text-slate-900 dark:text-white">
              {{ typeLabel(section.type) }}
            </span>
          </span>
          <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">
            {{ summaryFor(section) || t('showcase.editor.emptySection', 'Section vide') }}
          </span>
        </button>

        <div class="flex items-center gap-1">
          <button
            type="button"
            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
            :aria-label="t('showcase.editor.moveUp', 'Monter')"
            :disabled="index === 0 || busy"
            @click="move(index, -1)"
          >
            <ArrowUpIcon class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
            :aria-label="t('showcase.editor.moveDown', 'Descendre')"
            :disabled="index === orderedSections.length - 1 || busy"
            @click="move(index, 1)"
          >
            <ArrowDownIcon class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
            :aria-label="t('showcase.editor.duplicate', 'Dupliquer')"
            @click="$emit('duplicate', section)"
          >
            <DocumentDuplicateIcon class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
            :aria-label="t('showcase.editor.delete', 'Supprimer')"
            @click="$emit('remove', section)"
          >
            <TrashIcon class="h-4 w-4" />
          </button>
        </div>
      </li>
    </ol>

    <p v-if="orderedSections.length === 0" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
      {{ t('showcase.editor.noSections', 'Aucune section : ajoutez-en une pour composer la page.') }}
    </p>

    <details class="mt-4">
      <summary class="btn-secondary cursor-pointer py-2 text-center">
        {{ t('showcase.editor.addSection', 'Ajouter une section') }}
      </summary>
      <div class="mt-2 grid grid-cols-2 gap-2">
        <button
          v-for="type in availableTypes"
          :key="type"
          type="button"
          class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-2 text-left text-sm font-medium text-slate-700 hover:border-brand-400 hover:bg-brand-50/60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          :disabled="busy"
          @click="$emit('add', type)"
        >
          <component :is="iconFor(type)" class="h-4 w-4 text-brand-600 dark:text-brand-400" />
          {{ typeLabel(type) }}
        </button>
      </div>
    </details>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'
import {
  ArrowDownIcon,
  ArrowUpIcon,
  Bars3BottomLeftIcon,
  Bars3Icon,
  ChatBubbleLeftRightIcon,
  DocumentDuplicateIcon,
  EnvelopeIcon,
  PhotoIcon,
  SparklesIcon,
  Squares2X2Icon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import { SHOWCASE_SECTION_TYPES } from '@/constants/showcaseSections'

/**
 * Liste ordonnée des sections de la vitrine (BC-27 SHOWCASE, #6870).
 *
 * Réordonnancement par drag & drop HTML5 natif (aucune dépendance ajoutée,
 * décision du spike #6869) + boutons Monter/Descendre accessibles. L'ordre
 * cible est remonté par `reorder(ids)` — la vue appelle
 * `POST /showcase/sections/reorder` avec la liste complète des ids.
 */
const props = defineProps({
  sections: { type: Array, default: () => [] },
  selectedId: { type: Number, default: null },
  busy: { type: Boolean, default: false },
  availableTypes: { type: Array, default: () => [] },
})

const emit = defineEmits(['select', 'add', 'duplicate', 'remove', 'reorder'])

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const order = ref(props.sections.map((section) => section.id))
const dragIndex = ref(null)

watch(
  () => props.sections.map((section) => section.id).join(','),
  () => {
    order.value = props.sections.map((section) => section.id)
  },
)

const byId = computed(() => {
  const map = new Map()
  for (const section of props.sections) map.set(section.id, section)
  return map
})

const orderedSections = computed(() =>
  order.value.map((id) => byId.value.get(id)).filter(Boolean),
)

const ICONS = {
  hero: Squares2X2Icon,
  features: SparklesIcon,
  gallery: PhotoIcon,
  testimonials: ChatBubbleLeftRightIcon,
  contact: EnvelopeIcon,
  footer: Bars3BottomLeftIcon,
}

function iconFor(type) {
  return ICONS[type] || Squares2X2Icon
}

function typeLabel(type) {
  return t(`showcase.type.${type}`, type)
}

function summaryFor(section) {
  const spec = SHOWCASE_SECTION_TYPES[section.type]
  if (!spec) return ''
  const content = section.content && typeof section.content === 'object' ? section.content : {}

  for (const field of spec.fields) {
    if (field.localizable === false) continue
    const value = content[field.key]
    if (typeof value === 'string' && value.trim() !== '') return truncate(value)
  }

  if (spec.collection) {
    const rows = Array.isArray(content[spec.collection.key]) ? content[spec.collection.key] : []
    for (const row of rows) {
      if (!row || typeof row !== 'object') continue
      for (const field of spec.collection.fields) {
        if (field.localizable === false) continue
        const value = row[field.key]
        if (typeof value === 'string' && value.trim() !== '') return truncate(value)
      }
    }
  }

  return ''
}

function truncate(value) {
  return value.length > 60 ? `${value.slice(0, 57)}…` : value
}

function select(section) {
  emit('select', section)
}

function onDragStart(index, event) {
  dragIndex.value = index
  if (event?.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
  }
}

function onDragOver(index) {
  if (dragIndex.value === null || dragIndex.value === index) return
  const next = [...order.value]
  const [moved] = next.splice(dragIndex.value, 1)
  next.splice(index, 0, moved)
  order.value = next
  dragIndex.value = index
}

function onDrop() {
  commitOrder()
}

function onDragEnd() {
  commitOrder()
}

function commitOrder() {
  if (dragIndex.value === null) return
  dragIndex.value = null
  emit('reorder', [...order.value])
}

function move(index, delta) {
  const target = index + delta
  if (target < 0 || target >= order.value.length) return
  const next = [...order.value]
  const [moved] = next.splice(index, 1)
  next.splice(target, 0, moved)
  order.value = next
  emit('reorder', [...order.value])
}
</script>
