<template>
  <div>
    <PageHeader :title="title" />
    <StatusPanel
      :loading="!settings.loaded"
      :empty="settings.loaded && !html"
      :empty-text="$t('common.empty')"
    >
      <!-- html comes from the admin-managed settings and is sanitised by the API -->
      <div class="prose" dir="rtl" v-html="html" />
    </StatusPanel>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '@/components/PageHeader.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { useSettingsStore } from '@/stores/useSettingsStore'

// One page for the fixed texts: route meta says which setting and which title to use.
const route = useRoute()
const { t } = useI18n()
const settings = useSettingsStore()

const title = computed(() => t(route.meta.titleKey))
const html = computed(() => settings.data[route.meta.setting] || '')

onMounted(() => {
  if (!settings.loaded) settings.fetch()
})
</script>
