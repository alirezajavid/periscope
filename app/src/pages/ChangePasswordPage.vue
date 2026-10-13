<template>
  <q-page class="app-page">
    <PageHeader :title="$t('account.changePassword')" fallback="/account" />
    <q-banner v-if="message" class="bg-negative text-white q-mb-md" rounded dense>{{
      message
    }}</q-banner>
    <form novalidate @submit.prevent="onSubmit">
      <BaseInput
        v-model="form.current_password"
        :label="$t('account.currentPassword')"
        type="password"
        dir="ltr"
        autocomplete="current-password"
        :error="errors.current_password"
      />
      <BaseInput
        v-model="form.password"
        :label="$t('auth.newPassword')"
        type="password"
        dir="ltr"
        autocomplete="new-password"
        :error="errors.password"
      />
      <PasswordRules :password="form.password" />
      <BaseInput
        v-model="form.password_confirmation"
        :label="$t('auth.newPasswordConfirmation')"
        type="password"
        dir="ltr"
        autocomplete="new-password"
      />
      <p class="warn">{{ $t('reset.logoutWarning') }}</p>
      <q-btn
        class="btn"
        type="submit"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :label="$t('reset.save')"
        :loading="loading"
      />
    </form>
  </q-page>
</template>

<script setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import BaseInput from '@/components/BaseInput.vue'
import PageHeader from '@/components/PageHeader.vue'
import PasswordRules from '@/components/PasswordRules.vue'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/useAuthStore'

const router = useRouter()
const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const form = reactive({ current_password: '', password: '', password_confirmation: '' })

async function onSubmit() {
  // The API ends every session (this one too); the next screen is login.
  if (await submit(() => auth.changePassword(form))) router.replace({ name: 'login' })
}
</script>

<style scoped>
.warn {
  color: var(--amber);
  font-size: 13px;
  line-height: 1.9;
  margin: 18px 0;
}
</style>
