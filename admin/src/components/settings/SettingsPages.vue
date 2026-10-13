<template>
  <q-form class="column q-gutter-lg" @submit.prevent="submit">
    <q-banner v-if="message" class="bg-negative text-white" rounded dense>{{ message }}</q-banner>

    <section v-for="page in pages" :key="page.key">
      <div class="text-subtitle1 q-mb-sm">{{ $t(page.label) }}</div>
      <HtmlEditor v-model="draft[page.key]" />
      <div v-if="errors[page.key]" class="text-negative text-caption q-mt-xs">
        {{ errors[page.key] }}
      </div>
    </section>

    <div>
      <q-btn
        type="submit"
        color="primary"
        unelevated
        no-caps
        :label="$t('common.save')"
        :loading="loading"
      />
    </div>
  </q-form>
</template>

<script setup>
import { reactive } from 'vue'
import HtmlEditor from '@/components/HtmlEditor.vue'
import { useApiForm } from '@/composables/useApiForm'

const props = defineProps({
  settings: { type: Object, required: true },
  save: { type: Function, required: true },
})

const { loading, errors, message, submit: send } = useApiForm()

const pages = [
  { key: 'page_about', label: 'settings.pageAbout' },
  { key: 'page_terms', label: 'settings.pageTerms' },
  { key: 'page_install_guide', label: 'settings.pageInstall' },
]
const draft = reactive(Object.fromEntries(pages.map((p) => [p.key, props.settings[p.key] ?? ''])))

const submit = () => send(() => props.save({ ...draft }))
</script>
