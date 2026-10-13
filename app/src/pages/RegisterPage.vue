<template>
  <q-form class="column q-gutter-y-sm" @submit.prevent="onSubmit">
    <q-banner v-if="message" class="bg-negative text-white" dense rounded>{{ message }}</q-banner>

    <q-input
      v-model="form.name"
      autocomplete="name"
      outlined
      :label="$t('auth.name')"
      :error="!!errors.name"
      :error-message="errors.name"
    />
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
      autocomplete="new-password"
      outlined
      :label="$t('auth.password')"
      :hint="$t('auth.passwordHint')"
      :error="!!errors.password"
      :error-message="errors.password"
    />
    <q-input
      v-model="form.password_confirmation"
      type="password"
      dir="ltr"
      autocomplete="new-password"
      outlined
      :label="$t('auth.passwordConfirmation')"
    />
    <q-btn type="submit" color="primary" no-caps :label="$t('auth.register')" :loading="loading" />
    <q-btn flat no-caps dense :to="{ name: 'login' }" :label="$t('auth.haveAccount')" />
  </q-form>
</template>

<script setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const router = useRouter()
const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })

async function onSubmit() {
  if (await submit(() => auth.register(form))) router.push({ name: 'home' })
}
</script>
