<template>
  <div>
    <PageHeader :title="$t('forgot.title')" fallback="/login" />

    <div class="icon-circle"><q-icon name="vpn_key" size="36px" color="secondary" /></div>
    <p class="muted text">{{ $t('forgot.text') }}</p>

    <q-banner v-if="message" class="bg-negative text-white q-mb-md" rounded dense>{{
      message
    }}</q-banner>

    <form novalidate @submit.prevent="onSubmit">
      <BaseInput
        v-model="email"
        :label="$t('auth.email')"
        type="email"
        dir="ltr"
        autocomplete="email"
        :error="errors.email"
      />
      <q-btn
        class="btn q-mt-md"
        type="submit"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :label="$t('forgot.send')"
        :loading="loading"
      />
    </form>
    <p class="center q-mt-lg">
      <router-link class="btn-link" :to="{ name: 'login' }">{{ $t('forgot.back') }}</router-link>
    </p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseInput from '@/components/BaseInput.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const router = useRouter()
const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const email = ref('')

async function onSubmit() {
  if (await submit(() => auth.forgotPassword({ email: email.value }))) {
    router.replace({ name: 'reset-link-sent', query: { email: email.value } })
  }
}
</script>

<style scoped lang="scss">
.icon-circle {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: rgba($secondary, 0.2);
  display: grid;
  place-items: center;
  margin: 28px auto 24px;
}

.text {
  line-height: 2;
}

.center {
  text-align: center;
}
</style>
