<template>
  <q-form class="column q-gutter-y-sm" @submit.prevent="onSubmit">
    <q-banner v-if="route.query.expired" class="bg-warning text-dark" dense rounded>
      {{ $t('auth.sessionEnded') }}
    </q-banner>
    <q-banner v-if="message" class="bg-negative text-white" dense rounded>{{ message }}</q-banner>

    <q-input
      v-model="form.email"
      type="email"
      dir="ltr"
      autocomplete="email"
      outlined
      :label="$t('auth.email')"
      :error="!!errors.email"
      :error-message="errors.email"
    />
    <q-input
      v-model="form.password"
      type="password"
      dir="ltr"
      autocomplete="current-password"
      outlined
      :label="$t('auth.password')"
      :error="!!errors.password"
      :error-message="errors.password"
    />
    <q-btn type="submit" color="primary" no-caps :label="$t('auth.login')" :loading="loading" />
    <div class="row justify-between">
      <q-btn
        flat
        no-caps
        dense
        :to="{ name: 'forgot-password' }"
        :label="$t('auth.forgotPassword')"
      />
      <q-btn flat no-caps dense :to="{ name: 'register' }" :label="$t('auth.createAccount')" />
    </div>
  </q-form>
</template>

<script setup>
import { reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const form = reactive({ email: '', password: '' })

async function onSubmit() {
  if (await submit(() => auth.login(form))) router.push({ name: 'home' })
}
</script>
