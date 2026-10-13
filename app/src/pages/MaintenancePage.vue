<template>
  <div class="maintenance">
    <q-icon name="build_circle" size="72px" color="primary" />
    <h1>{{ $t('maintenance.title') }}</h1>
    <p class="muted">{{ message || $t('maintenance.text') }}</p>
    <q-btn flat no-caps color="primary" :label="$t('common.retry')" @click="reload" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSettingsStore } from '@/stores/useSettingsStore'

const settings = useSettingsStore()
const message = computed(() => settings.data.maintenance_message)

const reload = () => window.location.reload()
</script>

<style scoped lang="scss">
.maintenance {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-align: center;
  padding: 24px;

  h1 {
    font-size: 22px;
    margin: 12px 0 0;
  }
}
</style>
