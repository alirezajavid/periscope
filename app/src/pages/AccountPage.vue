<template>
  <q-page class="app-page has-miniplayer">
    <h1 class="page-title">{{ $t('account.title') }}</h1>

    <div class="who">
      <div class="avatar">{{ initial }}</div>
      <div>
        <div class="name">{{ auth.user?.name }}</div>
        <div class="muted contact" dir="ltr">{{ auth.user?.email || auth.user?.phone }}</div>
      </div>
    </div>

    <section class="card sub" :class="{ on: subscription.active }">
      <template v-if="subscription.active">
        <div class="row">
          <strong>{{ $t('account.activeSub') }}</strong>
          <span class="chip chip--ok">{{ $t('account.active') }}</span>
        </div>
        <div class="muted small">
          {{
            $t('account.until', {
              date: formatDate(subscription.endsAt),
              days: formatNumber(daysLeft),
            })
          }}
        </div>
        <q-linear-progress
          rounded
          size="8px"
          color="positive"
          track-color="grey-9"
          :value="ratio"
        />
        <q-btn
          class="btn"
          color="primary"
          text-color="dark"
          unelevated
          no-caps
          :label="$t('account.renew')"
          :to="{ name: 'plans' }"
        />
      </template>
      <template v-else>
        <strong>{{ $t('account.noSub') }}</strong>
        <div class="muted small">{{ $t('account.noSubText') }}</div>
        <q-btn
          class="btn"
          color="primary"
          text-color="dark"
          unelevated
          no-caps
          :label="$t('post.buy')"
          :to="{ name: 'plans' }"
        />
      </template>
    </section>

    <q-list class="card menu">
      <q-item clickable :to="{ name: 'payments' }">
        <q-item-section avatar><q-icon name="receipt_long" /></q-item-section>
        <q-item-section>{{ $t('account.paymentHistory') }}</q-item-section>
        <q-item-section side><q-icon name="chevron_left" /></q-item-section>
      </q-item>

      <q-item>
        <q-item-section avatar><q-icon name="dns" /></q-item-section>
        <q-item-section>{{ $t('account.server') }}</q-item-section>
        <q-item-section side>
          <q-btn-toggle
            :model-value="auth.user?.preferred_mirror"
            unelevated
            no-caps
            toggle-color="primary"
            toggle-text-color="dark"
            color="dark"
            :options="[
              { label: $t('account.serverIr'), value: 'ir' },
              { label: $t('account.serverIntl'), value: 'intl' },
            ]"
            @update:model-value="(value) => auth.updateProfile({ preferred_mirror: value })"
          />
        </q-item-section>
      </q-item>

      <q-item tag="label">
        <q-item-section avatar><q-icon name="mail_outline" /></q-item-section>
        <q-item-section>{{ $t('account.newEpisodeEmails') }}</q-item-section>
        <q-item-section side>
          <q-toggle
            :model-value="auth.user?.notify_new_episode"
            @update:model-value="(value) => auth.updateProfile({ notify_new_episode: value })"
          />
        </q-item-section>
      </q-item>

      <q-item clickable :to="{ name: 'terms' }">
        <q-item-section avatar><q-icon name="description" /></q-item-section>
        <q-item-section>{{ $t('account.terms') }}</q-item-section>
        <q-item-section side><q-icon name="chevron_left" /></q-item-section>
      </q-item>
      <q-item clickable :to="{ name: 'about' }">
        <q-item-section avatar><q-icon name="info_outline" /></q-item-section>
        <q-item-section>{{ $t('account.about') }}</q-item-section>
        <q-item-section side><q-icon name="chevron_left" /></q-item-section>
      </q-item>
      <q-item clickable :to="{ name: 'support' }">
        <q-item-section avatar><q-icon name="favorite_border" /></q-item-section>
        <q-item-section>{{ $t('account.support') }}</q-item-section>
        <q-item-section side><q-icon name="chevron_left" /></q-item-section>
      </q-item>
      <q-item clickable :to="{ name: 'install' }">
        <q-item-section avatar><q-icon name="install_mobile" /></q-item-section>
        <q-item-section>{{ $t('account.installGuide') }}</q-item-section>
        <q-item-section side><q-icon name="chevron_left" /></q-item-section>
      </q-item>
      <q-item v-if="auth.user?.email" clickable :to="{ name: 'change-password' }">
        <q-item-section avatar><q-icon name="lock_outline" /></q-item-section>
        <q-item-section>{{ $t('account.changePassword') }}</q-item-section>
        <q-item-section side><q-icon name="chevron_left" /></q-item-section>
      </q-item>
      <q-item clickable @click="signOutEverywhere">
        <q-item-section avatar><q-icon name="devices" /></q-item-section>
        <q-item-section>{{ $t('account.logoutAll') }}</q-item-section>
      </q-item>
      <q-item clickable class="text-negative" @click="signOut">
        <q-item-section avatar><q-icon name="logout" /></q-item-section>
        <q-item-section>{{ $t('auth.logout') }}</q-item-section>
      </q-item>
    </q-list>
  </q-page>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Dialog } from 'quasar'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/useAuthStore'
import { useSubscriptionStore } from '@/stores/useSubscriptionStore'
import { formatDate } from '@shared/utils/date.js'
import { formatNumber } from '@shared/utils/number.js'

const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const subscription = useSubscriptionStore()

const initial = computed(() => (auth.user?.name ?? '?').trim().charAt(0))
const MS_DAY = 86400000
const daysLeft = computed(() =>
  Math.max(0, Math.ceil((new Date(subscription.endsAt) - Date.now()) / MS_DAY)),
)
// Share of the current subscription period that is left (period start comes from the API)
const ratio = computed(() => {
  const current = subscription.status?.subscriptions?.find(
    (s) => new Date(s.starts_at) <= Date.now(),
  )
  if (!current) return 0
  const total = new Date(current.ends_at) - new Date(current.starts_at)
  return total > 0 ? Math.min(1, Math.max(0, (new Date(current.ends_at) - Date.now()) / total)) : 0
})

async function signOut() {
  await auth.logout()
  router.replace({ name: 'login' })
}

function signOutEverywhere() {
  Dialog.create({
    message: t('account.logoutAllConfirm'),
    cancel: t('common.cancel'),
    ok: { label: t('account.logoutAll'), color: 'negative' },
    dark: true,
  }).onOk(async () => {
    await auth.logoutAll()
    router.replace({ name: 'login' })
  })
}

onMounted(() => subscription.fetchStatus().catch(() => {}))
</script>

<style scoped lang="scss">
.who {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 20px;
}

.avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--purple);
  display: grid;
  place-items: center;
  font-size: 24px;
  font-weight: 700;
}

.name {
  font-size: 18px;
  font-weight: 700;
}

.contact {
  font-size: 13px;
  text-align: start;
}

.sub {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 18px;
  border: 1px solid var(--line);

  &.on {
    border-color: $positive;
  }
}

.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.small {
  font-size: 13px;
}

.menu {
  padding: 8px 4px;
}
</style>
