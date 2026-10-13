<template>
  <div>
    <PageHeader :title="$t('auth.createAccount')" fallback="/login" />

    <div class="welcome">
      <img src="@/assets/logo.png" alt="" />
      <span>{{ $t('auth.welcome') }}</span>
    </div>

    <q-banner v-if="message" class="bg-negative text-white q-mb-md" rounded dense>{{
      message
    }}</q-banner>

    <form novalidate @submit.prevent="onSubmit">
      <BaseInput
        v-model="form.name"
        :label="$t('auth.name')"
        autocomplete="name"
        :error="errors.name"
      />
      <BaseInput
        v-model="form.email"
        :label="$t('auth.email')"
        type="email"
        dir="ltr"
        autocomplete="email"
        :error="errors.email"
      />
      <BaseInput
        v-model="form.password"
        :label="$t('auth.password')"
        type="password"
        dir="ltr"
        autocomplete="new-password"
        :error="errors.password"
      />
      <PasswordRules :password="form.password" />
      <BaseInput
        v-model="form.password_confirmation"
        :label="$t('auth.passwordConfirmation')"
        type="password"
        dir="ltr"
        autocomplete="new-password"
        :error="mismatch"
      />

      <q-checkbox v-model="accepted" class="terms" dense>
        <span class="muted">
          {{ $t('auth.acceptTerms') }}
          <router-link class="btn-link" :to="{ name: 'terms' }" @click.stop>{{
            $t('account.terms')
          }}</router-link>
        </span>
      </q-checkbox>
      <div v-if="showTermsError" class="text-negative terms-error">
        {{ $t('auth.termsRequired') }}
      </div>

      <q-btn
        class="btn q-mt-md"
        type="submit"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :label="$t('auth.register')"
        :loading="loading"
      />
    </form>

    <p class="center muted q-mt-lg">
      {{ $t('auth.haveAccount') }}
      <router-link class="btn-link strong" :to="{ name: 'login' }">{{
        $t('auth.login')
      }}</router-link>
    </p>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/BaseInput.vue'
import PageHeader from '@/components/PageHeader.vue'
import PasswordRules from '@/components/PasswordRules.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const accepted = ref(false)
const showTermsError = ref(false)

const mismatch = computed(() =>
  form.password_confirmation && form.password !== form.password_confirmation
    ? t('auth.passwordsDiffer')
    : '',
)

async function onSubmit() {
  showTermsError.value = !accepted.value
  if (showTermsError.value || mismatch.value) return
  if (await submit(() => auth.register(form))) router.replace({ name: 'verify-email' })
}
</script>

<style scoped lang="scss">
.welcome {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 16px;
  margin-bottom: 4px;

  img {
    width: 48px;
    height: 48px;
    border-radius: 10px;
  }
}

.terms {
  margin-top: 18px;
  align-items: flex-start;
  font-size: 13px;
}

.terms-error {
  font-size: 13px;
  margin-top: 4px;
}

.center {
  text-align: center;
}

.strong {
  font-weight: 700;
}
</style>
