<template>
  <q-form class="column q-gutter-y-sm" @submit.prevent="onSubmit">
    <q-banner v-if="done" class="bg-positive text-white" dense rounded>
      {{ $t('auth.passwordResetDone') }}
    </q-banner>
    <q-banner v-if="message || errors.email" class="bg-negative text-white" dense rounded>
      {{ message || errors.email }}
    </q-banner>

    <q-input
      v-model="form.password"
      type="password"
      dir="ltr"
      autocomplete="new-password"
      outlined
      :label="$t('auth.newPassword')"
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
    <q-btn
      v-if="!done"
      type="submit"
      color="primary"
      no-caps
      :label="$t('auth.resetPassword')"
      :loading="loading"
    />
    <q-btn
      :flat="!done"
      :color="done ? 'primary' : undefined"
      no-caps
      dense
      :to="{ name: 'login' }"
      :label="$t('auth.backToLogin')"
    />
  </q-form>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const route = useRoute()
const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const form = reactive({ password: '', password_confirmation: '' })
const done = ref(false)

async function onSubmit() {
  done.value = await submit(() =>
    auth.resetPassword({ ...form, token: route.query.token, email: route.query.email }),
  )
}
</script>
