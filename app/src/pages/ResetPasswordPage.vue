<template>
  <div>
    <div class="head">
      <img src="@/assets/logo.png" alt="" />
      <h1>{{ $t('reset.title') }}</h1>
    </div>
    <p class="muted for">{{ $t('reset.forEmail', { email: route.query.email }) }}</p>

    <q-banner v-if="done" class="bg-positive text-white q-mb-md" rounded dense>{{
      $t('reset.done')
    }}</q-banner>
    <q-banner v-if="problem" class="bg-negative text-white q-mb-md" rounded dense>{{
      problem
    }}</q-banner>

    <form novalidate @submit.prevent="onSubmit">
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
      <PasswordRules
        :password="form.password"
        :confirmation="form.password_confirmation"
        class="match-only"
      />

      <p class="warn">{{ $t('reset.logoutWarning') }}</p>

      <q-btn
        v-if="!done"
        class="btn"
        type="submit"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :label="$t('reset.save')"
        :loading="loading"
      />
      <q-btn
        v-else
        class="btn"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :label="$t('auth.login')"
        :to="{ name: 'login' }"
      />
    </form>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/BaseInput.vue'
import PasswordRules from '@/components/PasswordRules.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useApiForm } from '@/composables/useApiForm'

const route = useRoute()
const { t } = useI18n()
const auth = useAuthStore()
const { loading, errors, message, submit } = useApiForm()
const form = reactive({ password: '', password_confirmation: '' })
const done = ref(false)

// The API answers an expired or used token with an error on `email`.
const problem = computed(() => (errors.email ? t('reset.invalidLink') : message.value))

async function onSubmit() {
  done.value = await submit(() =>
    auth.resetPassword({ ...form, token: route.query.token, email: route.query.email }),
  )
}
</script>

<style scoped lang="scss">
.head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 8px 0 4px;

  img {
    width: 44px;
    height: 44px;
    border-radius: 10px;
  }

  h1 {
    font-size: 22px;
    font-weight: 700;
    margin: 0;
  }
}

.for {
  margin: 0 0 8px;
  unicode-bidi: plaintext;
}

// Only the "both match" line of the second list is shown under the confirmation field
.match-only :deep(li:not(:last-child)) {
  display: none;
}

.warn {
  color: var(--amber);
  font-size: 13px;
  line-height: 1.9;
  margin: 18px 0;
}
</style>
