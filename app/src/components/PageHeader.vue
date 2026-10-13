<template>
  <header class="page-header">
    <q-btn
      v-if="back"
      flat
      round
      dense
      icon="arrow_forward"
      :aria-label="$t('common.back')"
      @click="goBack"
    />
    <h1 class="title">{{ title }}</h1>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  title: { type: String, default: '' },
  back: { type: Boolean, default: true },
  // Where "back" goes when there is no history to return to (e.g. a page opened from a link)
  fallback: { type: [String, Object], default: '/' },
})

const router = useRouter()

function goBack() {
  if (window.history.state?.back) router.back()
  else router.replace(props.fallback)
}
</script>

<style scoped lang="scss">
.page-header {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 48px;
  margin-bottom: 8px;
}

.title {
  font-size: 22px;
  font-weight: 700;
  margin: 0;
  line-height: 1.5;
}
</style>
