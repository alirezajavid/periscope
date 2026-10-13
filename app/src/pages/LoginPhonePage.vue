<template>
  <div>
    <PageHeader :title="$t('auth.loginWithPhone')" fallback="/login" />

    <q-banner v-if="message" class="bg-negative text-white q-mb-md" rounded dense>{{
      message
    }}</q-banner>
    <p class="muted hint">{{ $t('auth.foreignHint') }}</p>

    <form v-if="step === 'phone'" novalidate @submit.prevent="sendCode">
      <BaseInput
        v-model="phone"
        :label="$t('auth.phone')"
        type="tel"
        dir="ltr"
        inputmode="tel"
        autocomplete="tel"
        :placeholder="$t('auth.phonePlaceholder')"
        :error="errors.phone"
      />
      <q-btn
        class="btn q-mt-md"
        type="submit"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :label="$t('auth.sendCode')"
        :loading="loading"
      />
    </form>

    <form v-else novalidate @submit.prevent="verify">
      <p class="muted">{{ $t('auth.codeSentTo', { phone }) }}</p>
      <BaseInput
        v-model="code"
        :label="$t('auth.code')"
        dir="ltr"
        inputmode="numeric"
        autocomplete="one-time-code"
        maxlength="6"
        :error="errors.code"
      />
      <BaseInput
        v-if="needsName"
        v-model="name"
        :label="$t('auth.name')"
        autocomplete="name"
        :error="errors.name"
      />
      <p v-if="needsName" class="muted hint">{{ $t('auth.enterName') }}</p>
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
      <q-btn
        class="btn q-mt-sm"
        flat
        no-caps
        color="primary"
        :disable="countdown.active.value"
        :label="
          countdown.active.value
            ? $t('auth.resendIn', { time: countdown.label.value })
            : $t('auth.resend')
        "
        @click="sendCode"
      />
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseInput from '@/components/BaseInput.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'
import { useCountdown } from '@/composables/useCountdown'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const countdown = useCountdown(60)
const { loading, errors, message, submit } = useApiForm()
const step = ref('phone')
const phone = ref('')
const code = ref('')
const name = ref('')
const needsName = ref(false)

async function sendCode() {
  if (await submit(() => auth.sendOtp(phone.value))) {
    step.value = 'code'
    countdown.start()
  }
}

async function verify() {
  const ok = await submit(() =>
    auth.otpLogin({ phone: phone.value, code: code.value, name: name.value || undefined }),
  )
  if (ok) return router.replace(route.query.redirect || { name: 'home' })
  // A first-time number must give a name; the code is still valid, so just ask for it.
  if (errors.name) needsName.value = true
}
</script>

<style scoped lang="scss">
.hint {
  font-size: 13px;
  line-height: 1.9;
}
</style>
