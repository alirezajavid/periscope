<template>
  <q-page class="page">
    <h1 class="page-title">{{ $t('settings.title') }}</h1>

    <q-tabs
      v-model="tab"
      align="left"
      dense
      class="text-grey-8"
      active-color="primary"
      outside-arrows
      mobile-arrows
    >
      <q-tab
        v-for="name in tabs"
        :key="name"
        :name="name"
        no-caps
        :label="$t(`settings.tabs.${name}`)"
      />
    </q-tabs>
    <q-separator />

    <q-tab-panels v-model="tab" animated class="bg-transparent">
      <q-tab-panel name="links">
        <StatusPanel :loading="loading" :error="error" :retry="load">
          <SettingsLinks v-if="settings" :settings="settings" :save="save" />
        </StatusPanel>
      </q-tab-panel>
      <q-tab-panel name="pages">
        <StatusPanel :loading="loading" :error="error" :retry="load">
          <SettingsPages v-if="settings" :settings="settings" :save="save" />
        </StatusPanel>
      </q-tab-panel>
      <q-tab-panel name="maintenance">
        <StatusPanel :loading="loading" :error="error" :retry="load">
          <SettingsMaintenance v-if="settings" :settings="settings" :save="save" />
        </StatusPanel>
      </q-tab-panel>
      <q-tab-panel name="plans"><SettingsPlans /></q-tab-panel>
      <q-tab-panel name="methods"><SettingsMethods /></q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useI18n } from 'vue-i18n'
import StatusPanel from '@/components/StatusPanel.vue'
import SettingsLinks from '@/components/settings/SettingsLinks.vue'
import SettingsPages from '@/components/settings/SettingsPages.vue'
import SettingsMaintenance from '@/components/settings/SettingsMaintenance.vue'
import SettingsPlans from '@/components/settings/SettingsPlans.vue'
import SettingsMethods from '@/components/settings/SettingsMethods.vue'
import { useAdminSettingsStore } from '@/stores/useAdminSettingsStore'
import { useAsync } from '@/composables/useAsync'

const { t } = useI18n()
const store = useAdminSettingsStore()

const tabs = ['links', 'pages', 'plans', 'methods', 'maintenance']
const tab = ref('links')
const settings = ref(null)

const {
  loading,
  error,
  run: load,
} = useAsync(
  async () => {
    settings.value = await store.load()
  },
  { fallbackMessage: t('common.genericError') },
)

/** Shared by the setting tabs: sends a subset of keys and keeps the returned values. */
async function save(payload) {
  settings.value = await store.save(payload)
  Notify.create({ type: 'positive', message: t('settings.saved') })
}

onMounted(load)
</script>
