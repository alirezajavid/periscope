<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <q-banner
        v-if="auth.isLoggedIn && !auth.emailVerified"
        class="bg-warning text-dark verify-banner"
        dense
      >
        {{ $t('auth.verifyBanner') }}
        <template #action>
          <q-btn
            flat
            dense
            no-caps
            :label="$t('auth.verifyAction')"
            :to="{ name: 'verify-email' }"
          />
        </template>
      </q-banner>
      <q-banner v-if="!online" class="bg-grey-9 offline-banner" dense>
        <q-icon name="cloud_off" /> {{ $t('common.offline') }}
      </q-banner>
      <router-view />
    </q-page-container>

    <MiniPlayer />
    <TabBar />
  </q-layout>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import MiniPlayer from '@/components/MiniPlayer.vue'
import TabBar from '@/components/TabBar.vue'
import { useAuthStore } from '@/stores/useAuthStore'

const auth = useAuthStore()
const online = ref(navigator.onLine)
const setOnline = () => (online.value = navigator.onLine)

onMounted(() => {
  window.addEventListener('online', setOnline)
  window.addEventListener('offline', setOnline)
})
onBeforeUnmount(() => {
  window.removeEventListener('online', setOnline)
  window.removeEventListener('offline', setOnline)
})
</script>

<style scoped>
.offline-banner {
  font-size: 13px;
}

.verify-banner {
  font-size: 13px;
}
</style>
