<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated>
      <q-toolbar>
        <q-toolbar-title>{{ $t('app.name') }}</q-toolbar-title>
        <q-btn flat no-caps :label="$t('auth.logout')" :loading="loggingOut" @click="onLogout" />
      </q-toolbar>
      <q-banner v-if="!auth.emailVerified" class="bg-warning text-dark" dense>
        {{ $t('auth.verifyBanner') }}
        <template #action>
          <q-btn
            flat
            no-caps
            :label="$t('auth.resendVerification')"
            :disable="resent"
            @click="onResend"
          />
        </template>
      </q-banner>
    </q-header>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

const auth = useAuthStore()
const router = useRouter()
const loggingOut = ref(false)
const resent = ref(false)

async function onLogout() {
  loggingOut.value = true
  try {
    await auth.logout()
  } finally {
    loggingOut.value = false
  }
  router.push({ name: 'login' })
}

async function onResend() {
  await auth.resendVerification()
  resent.value = true
}
</script>
