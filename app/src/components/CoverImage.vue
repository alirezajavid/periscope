<template>
  <img class="cover" :src="url || fallback" :alt="alt" :style="{ borderRadius: radius }" />
</template>

<script setup>
import fallback from '@/assets/logo.png'

// Cover of a post; the podcast logo stands in when a post has none.
defineProps({
  url: { type: String, default: null },
  alt: { type: String, default: '' },
  radius: { type: String, default: '12px' },
})
</script>

<style scoped>
.cover {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
