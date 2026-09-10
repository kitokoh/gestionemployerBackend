<template>
  <textarea
    v-if="field.widget === 'textarea'"
    :id="id"
    :value="value"
    :rows="field.rows || 3"
    :maxlength="field.maxlength || undefined"
    :required="required"
    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
    @input="$emit('update:value', $event.target.value)"
  ></textarea>

  <ShowcaseMediaPicker
    v-else-if="field.widget === 'media'"
    v-model="model"
    :media="media"
    :slug="slug"
    :kind="kind"
    :section-id="sectionId"
    @uploaded="$emit('uploaded', $event)"
    @error="$emit('error', $event)"
  />

  <input
    v-else
    :id="id"
    :value="value"
    :type="inputType"
    :maxlength="field.maxlength || undefined"
    :required="required"
    :placeholder="field.placeholder || undefined"
    class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
    @input="$emit('update:value', $event.target.value)"
  />
</template>

<script setup>
import { computed } from 'vue'
import ShowcaseMediaPicker from '@/components/showcase/ShowcaseMediaPicker.vue'

/**
 * Champ de saisie d'une section de vitrine : rendu selon le `widget` du
 * contrat de section (`text` | `textarea` | `url` | `email` | `media`).
 *
 * Composant purement présentationnel — la valeur remonte par
 * `update:value` (chaîne) et l'upload par `uploaded` (média #6872).
 */
const props = defineProps({
  id: { type: String, default: undefined },
  field: { type: Object, required: true },
  value: { type: String, default: '' },
  required: { type: Boolean, default: false },
  slug: { type: String, default: '' },
  media: { type: Array, default: () => [] },
  sectionId: { type: Number, default: null },
  kind: { type: String, default: 'image' },
})

const emit = defineEmits(['update:value', 'uploaded', 'error'])

/** Relais `v-model` vers le picker média (`update:value` vers le parent). */
const model = computed({
  get: () => props.value,
  set: (value) => emit('update:value', value),
})

const inputType = computed(() => (props.field.widget === 'email' ? 'email' : props.field.widget === 'url' ? 'url' : 'text'))
</script>
