<template>
  <div class="login">
    <img class="logo" src="@/assets/logo.png" alt="" />
    <h1 class="name">{{ $t('app.title') }}</h1>
    <p class="muted tagline">{{ $t('app.tagline') }}</p>

    <q-banner v-if="notice" class="bg-warning text-dark q-mb-md" rounded dense>{{
      notice
    }}</q-banner>
    <q-banner v-if="message" class="bg-negative text-white q-mb-md" rounded dense>{{
      message
    }}</q-banner>

    <form novalidate @submit.prevent="onSubmit">
      <BaseInput
        v-model="form.email"
        :label="$t('auth.email')"
        type="email"
        dir="ltr"
        autocomplete="email"
        :placeholder="$t('auth.emailPlaceholder')"
        :error="errors.email"
      />
      <BaseInput
        v-model="form.password"
        :label="$t('auth.password')"
        type="password"
        dir="ltr"
        autocomplete="current-password"
        :error="errors.password"
      />
      <q-btn
        class="btn q-mt-md"
        type="submit"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :label="$t('auth.login')"
        :loading="loading"
      />
    </form>

    <div class="links">
      <router-link class="btn-link" :to="{ name: 'forgot-password' }">{{
        $t('auth.forgotPasswordLink')
      }}</router-link>
      <span class="muted">
        {{ $t('auth.noAccount') }}
        <router-link class="btn-link strong" :to="{ name: 'register' }">{{
          $t('auth.register')
        }}</router-link>
      </span>
    </div>

    <div class="divider muted">{{ $t('common.or') }}</div>
    <q-btn
      class="btn alt"
      outline
      no-caps
      color="primary"
      icon="smartphone"
      :label="$t('auth.loginWithPhone')"
      :to="{ name: 'login-phone' }"
    />
    <q-btn
      class="btn alt q-mt-sm"
      outline
      no-caps
      color="primary"
      icon="login"
      :label="$t('auth.loginWithGoogle')"
      @click="loginWithGoogle"
    />
  </div>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/BaseInput.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const form = reactive({ email: '', password: '' })

const notice = computed(() => {
  if (route.query.expired) return t('auth.sessionEnded')
  if (route.query.error === 'google') return t('auth.googleFailed')
  if (route.query.error === 'blocked') return t('auth.blocked')
  return ''
})

async function onSubmit() {
  if (await submit(() => auth.login(form))) router.replace(route.query.redirect || { name: 'home' })
}

// Google's OAuth flow starts on the API (a full-page redirect) and comes back to the app.
function loginWithGoogle() {
  window.location.href = `${import.meta.env.API_URL}/api/auth/google/redirect`
}
</script>

<style scoped lang="scss">
.login {
  padding-top: 64px;
  text-align: center;
}

.logo {
  width: 96px;
  height: 96px;
  border-radius: 20px;
}

.name {
  font-size: 22px;
  font-weight: 700;
  margin: 20px 0 8px;
}

.tagline {
  margin: 0 0 36px;
}

form {
  text-align: start;
}

.links {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  margin: 22px 0;
}

.strong {
  font-weight: 700;
}

.divider {
  margin: 8px 0 16px;
  font-size: 13px;
}

.alt {
  border-radius: 16px;
}
</style>
