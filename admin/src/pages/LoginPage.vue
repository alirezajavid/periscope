<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <q-page class="flex flex-center bg-grey-2">
        <q-card class="login-card q-pa-lg">
          <h1 class="text-h5 q-mt-none">{{ $t('login.title') }}</h1>
          <q-banner v-if="route.query.expired" class="bg-warning text-dark q-mb-md" rounded dense>{{
            $t('login.expired')
          }}</q-banner>
          <q-banner v-if="message" class="bg-negative text-white q-mb-md" rounded dense>{{
            message
          }}</q-banner>

          <!-- Step 1: password -->
          <q-form v-if="step === 'password'" @submit.prevent="submitPassword">
            <q-input
              v-model="form.email"
              :label="$t('login.email')"
              type="email"
              outlined
              dir="ltr"
              autocomplete="username"
              :error="!!errors.email"
              :error-message="errors.email"
              class="q-mb-md"
            />
            <q-input
              v-model="form.password"
              :label="$t('login.password')"
              type="password"
              outlined
              dir="ltr"
              autocomplete="current-password"
              :error="!!errors.password"
              :error-message="errors.password"
            />
            <q-btn
              class="full-width q-mt-lg"
              type="submit"
              color="primary"
              unelevated
              no-caps
              :label="$t('login.submit')"
              :loading="loading"
            />
          </q-form>

          <!-- Step 2a (first login): set up the authenticator app -->
          <q-form v-else-if="step === 'setup'" @submit.prevent="submitSetup">
            <h2 class="text-subtitle1">{{ $t('login.setupTitle') }}</h2>
            <p class="text-grey-8">{{ $t('login.setupText') }}</p>
            <div class="text-center">
              <img v-if="qrSrc" class="qr" :src="qrSrc" alt="QR" />
            </div>
            <div class="text-caption text-grey-7 q-mt-sm">{{ $t('login.secret') }}</div>
            <div class="ltr text-body1 q-mb-md">{{ setup.secret }}</div>

            <div class="text-subtitle2">{{ $t('login.recoveryTitle') }}</div>
            <p class="text-caption text-grey-8">{{ $t('login.recoveryText') }}</p>
            <div class="recovery ltr">
              <code v-for="c in setup.recovery_codes" :key="c">{{ c }}</code>
            </div>

            <q-input
              v-model="code"
              :label="$t('login.code')"
              outlined
              dir="ltr"
              inputmode="numeric"
              maxlength="6"
              autocomplete="one-time-code"
              :error="!!errors.code"
              :error-message="errors.code"
              class="q-mt-md"
            />
            <q-btn
              class="full-width q-mt-md"
              type="submit"
              color="primary"
              unelevated
              no-caps
              :label="$t('login.confirm')"
              :loading="loading"
            />
          </q-form>

          <!-- Step 2b: code from the app, or a recovery code -->
          <q-form v-else @submit.prevent="submitChallenge">
            <h2 class="text-subtitle1">{{ $t('login.challengeTitle') }}</h2>
            <p class="text-grey-8">{{ $t('login.challengeText') }}</p>
            <q-input
              v-if="!useRecovery"
              v-model="code"
              :label="$t('login.code')"
              outlined
              dir="ltr"
              inputmode="numeric"
              maxlength="6"
              autocomplete="one-time-code"
              autofocus
              :error="!!errors.code"
              :error-message="errors.code"
            />
            <q-input
              v-else
              v-model="recovery"
              :label="$t('login.recoveryCode')"
              outlined
              dir="ltr"
              autofocus
              :error="!!errors.code"
              :error-message="errors.code"
            />
            <q-btn
              class="full-width q-mt-md"
              type="submit"
              color="primary"
              unelevated
              no-caps
              :label="$t('login.submit')"
              :loading="loading"
            />
            <q-btn
              class="full-width q-mt-sm"
              flat
              no-caps
              color="primary"
              :label="useRecovery ? $t('login.useCode') : $t('login.useRecovery')"
              @click="useRecovery = !useRecovery"
            />
          </q-form>
        </q-card>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdminAuthStore } from '@/stores/useAdminAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const route = useRoute()
const router = useRouter()
const auth = useAdminAuthStore()
const { loading, errors, message, submit } = useApiForm()

const step = ref('password') // password | setup | challenge
const form = reactive({ email: '', password: '' })
const code = ref('')
const recovery = ref('')
const useRecovery = ref(false)
const setup = ref({})

// An <img> never runs scripts, so the API's SVG is shown this way instead of with v-html
const qrSrc = computed(() =>
  setup.value.qr_svg ? `data:image/svg+xml;base64,${btoa(setup.value.qr_svg)}` : '',
)

const done = () => router.replace({ name: 'posts' })

async function submitPassword() {
  let status
  const ok = await submit(async () => (status = await auth.login(form)))
  if (!ok) return
  if (status === 'two_factor_setup_required') {
    if (await submit(async () => (setup.value = await auth.startSetup()))) step.value = 'setup'
  } else {
    step.value = 'challenge'
  }
}

async function submitSetup() {
  if (await submit(() => auth.confirmSetup(code.value))) done()
}

async function submitChallenge() {
  const payload = useRecovery.value ? { recovery_code: recovery.value } : { code: code.value }
  if (await submit(() => auth.challenge(payload))) done()
}
</script>

<style scoped>
.login-card {
  width: 100%;
  max-width: 440px;
}

.qr {
  width: 200px;
  height: 200px;
}

.recovery {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4px 12px;
}
</style>
