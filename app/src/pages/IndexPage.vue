<template>
  <q-page class="flex flex-center">
    <div class="text-h6">{{ $t('home.welcome', { name: auth.user?.name }) }}</div>
  </q-page>
</template>

<script setup>
import { useAuthStore } from '@/stores/useAuthStore'

const auth = useAuthStore()
</script>
