<template>
  <div class="center-page">
    <div class="icon-circle"><q-icon name="check_circle" size="44px" color="positive" /></div>
    <h1 class="title">{{ $t('emailVerified.title') }}</h1>
    <p class="muted">{{ $t('emailVerified.text') }}</p>
    <q-btn
      class="btn"
      color="primary"
      text-color="dark"
      unelevated
      no-caps
      :label="$t('common.continue')"
      :to="{ name: 'home' }"
    />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/useAuthStore'

// Opened from the email link: refresh the cached user so the "unverified" banner disappears.
onMounted(() => useAuthStore().fetchUser())
</script>

<style scoped lang="scss">
.center-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding-top: 96px;
}

.icon-circle {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: rgba($accent, 0.18);
  display: grid;
  place-items: center;
  margin-bottom: 24px;
}

.title {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 8px;
}
</style>
