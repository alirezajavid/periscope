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
      <router-view />
    </q-page-container>

    <MiniPlayer />
    <TabBar />
  </q-layout>
</template>

<script setup>
import MiniPlayer from '@/components/MiniPlayer.vue'
import TabBar from '@/components/TabBar.vue'
import { useAuthStore } from '@/stores/useAuthStore'

const auth = useAuthStore()
</script>

<style scoped>
.verify-banner {
  font-size: 13px;
}
</style>
