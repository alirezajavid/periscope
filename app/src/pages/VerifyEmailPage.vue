<template>
  <div class="center-page">
    <q-btn
      class="skip"
      flat
      round
      icon="arrow_forward"
      :aria-label="$t('verify.skip')"
      :to="{ name: 'home' }"
    />

    <div class="icon-circle"><q-icon name="mail_outline" size="40px" color="primary" /></div>
    <h1 class="title">{{ $t('verify.title') }}</h1>
    <p class="muted text">{{ $t('verify.text', { email: auth.user?.email }) }}</p>
    <p class="muted small">{{ $t('verify.spam') }}</p>

    <q-banner v-if="notice" class="bg-positive text-white q-mb-md" rounded dense>{{
      notice
    }}</q-banner>

    <q-btn
      class="btn resend"
      unelevated
      no-caps
      :disable="countdown.active.value"
      :label="
        countdown.active.value
          ? $t('auth.resendIn', { time: countdown.label.value })
          : $t('auth.resend')
      "
      @click="resend"
    />

    <div class="edit">
      <template v-if="!editing">
        <a class="btn-link" href="#" @click.prevent="editing = true">
          {{ $t('verify.wrongEmail') }} {{ $t('verify.edit') }}
        </a>
      </template>
      <form v-else novalidate @submit.prevent="saveEmail">
        <BaseInput
          v-model="newEmail"
          :label="$t('verify.newEmail')"
          type="email"
          dir="ltr"
          :error="errors.email"
        />
        <q-btn
          class="btn q-mt-sm"
          color="primary"
          text-color="dark"
          unelevated
          no-caps
          type="submit"
          :label="$t('verify.update')"
          :loading="loading"
        />
      </form>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/BaseInput.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'
import { useCountdown } from '@/composables/useCountdown'

const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const countdown = useCountdown(60)
const { loading, errors, submit } = useApiForm()
const notice = ref('')
const editing = ref(false)
const newEmail = ref('')
let poll = null

async function resend() {
  await auth.resendVerification()
  notice.value = t('verify.sent')
  countdown.start()
}

async function saveEmail() {
  if (await submit(() => auth.updateEmail(newEmail.value))) {
    editing.value = false
    notice.value = t('verify.sent')
    countdown.start()
  }
}

// The link in the email opens in this or another browser; notice the verification and move on.
onMounted(() => {
  countdown.start()
  poll = setInterval(async () => {
    await auth.fetchUser()
    if (auth.emailVerified) router.replace({ name: 'email-verified' })
  }, 5000)
})
onBeforeUnmount(() => clearInterval(poll))
</script>

<style scoped lang="scss">
.center-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding-top: 24px;
  position: relative;
}

.skip {
  position: absolute;
  inset-inline-start: 0;
  top: 8px;
}

.icon-circle {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: rgba($primary, 0.18);
  display: grid;
  place-items: center;
  margin: 72px 0 24px;
}

.title {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 12px;
}

.text {
  line-height: 2;
  margin: 0 0 8px;
}

.small {
  font-size: 13px;
  margin: 8px 0 24px;
}

.resend {
  background: var(--surface-2);
  color: var(--muted);
}

.edit {
  margin-top: 20px;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-align: start;
}
</style>
