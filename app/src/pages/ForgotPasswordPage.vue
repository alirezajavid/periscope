<template>
  <q-form class="column q-gutter-y-sm" @submit.prevent="onSubmit">
    <q-banner v-if="sent" class="bg-positive text-white" dense rounded>
      {{ $t('auth.resetLinkSent') }}
    </q-banner>
    <q-banner v-if="message" class="bg-negative text-white" dense rounded>{{ message }}</q-banner>

    <p>{{ $t('auth.forgotPasswordIntro') }}</p>
    <q-input
      v-model="email"
      type="email"
      dir="ltr"
      autocomplete="email"
      outlined
      :label="$t('auth.email')"
      :error="!!errors.email"
      :error-message="errors.email"
    />
    <q-btn
      type="submit"
      color="primary"
      no-caps
      :label="$t('auth.sendResetLink')"
      :loading="loading"
    />
    <q-btn flat no-caps dense :to="{ name: 'login' }" :label="$t('auth.backToLogin')" />
  </q-form>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const email = ref('')
const sent = ref(false)

async function onSubmit() {
  sent.value = await submit(() => auth.forgotPassword({ email: email.value }))
}
</script>
