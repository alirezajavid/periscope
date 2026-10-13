<template>
  <div v-if="loading" class="status" role="status">
    <q-spinner color="primary" size="32px" />
    <span class="muted">{{ $t('common.loading') }}</span>
  </div>
  <div v-else-if="error" class="status" role="alert">
    <q-icon name="error_outline" size="36px" color="negative" />
    <span>{{ error }}</span>
    <q-btn v-if="retry" flat no-caps color="primary" :label="$t('common.retry')" @click="retry" />
  </div>
  <div v-else-if="empty" class="status">
    <q-icon :name="icon" size="36px" class="muted" />
    <span class="muted">{{ emptyText || $t('common.empty') }}</span>
  </div>
  <slot v-else />
</template>

<script setup>
// One place for the three non-happy states of any list or detail view.
defineProps({
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  empty: { type: Boolean, default: false },
  emptyText: { type: String, default: '' },
  icon: { type: String, default: 'inbox' },
  retry: { type: Function, default: null },
})
</script>

<style scoped lang="scss">
.status {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 48px 16px;
  text-align: center;
}
</style>
