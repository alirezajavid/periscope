<template>
  <q-page class="page">
    <div class="toolbar-row">
      <q-btn
        flat
        round
        dense
        icon="arrow_forward"
        :to="{ name: 'users' }"
        :aria-label="$t('common.back')"
      />
      <h1 class="page-title q-ma-none">{{ user?.name }}</h1>
    </div>

    <StatusPanel :loading="loading" :error="error" :retry="load">
      <div v-if="user" class="column q-gutter-md">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 q-mb-sm">{{ $t('users.detail.info') }}</div>
            <q-list dense>
              <q-item>
                <q-item-section>{{ $t('users.email') }}</q-item-section>
                <q-item-section side class="ltr">{{ user.email || '—' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>{{ $t('users.phone') }}</q-item-section>
                <q-item-section side class="ltr">{{ user.phone || '—' }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>{{ $t('users.status') }}</q-item-section>
                <q-item-section side>
                  <q-badge
                    :color="user.status === 'active' ? 'positive' : 'negative'"
                    :label="$t(`users.statusValue.${user.status}`)"
                  />
                </q-item-section>
              </q-item>
              <q-item>
                <q-item-section>{{ $t('users.subscribed') }}</q-item-section>
                <q-item-section side>{{
                  $t(`users.subscribedValue.${user.subscribed ? 1 : 0}`)
                }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>{{ $t('users.detail.mirror') }}</q-item-section>
                <q-item-section side>{{
                  $t(`users.detail.mirrorValue.${user.preferred_mirror}`)
                }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>{{ $t('users.createdAt') }}</q-item-section>
                <q-item-section side>{{ formatDateTime(user.created_at) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>{{ $t('users.lastLogin') }}</q-item-section>
                <q-item-section side>{{
                  user.last_login_at ? formatDateTime(user.last_login_at) : $t('users.never')
                }}</q-item-section>
              </q-item>
            </q-list>
            <div class="text-subtitle2 q-mt-md">{{ $t('users.detail.watermark') }}</div>
            <div class="ltr text-body1 payload">{{ user.watermark_payload }}</div>
            <div class="text-caption text-grey-7">{{ $t('users.detail.watermarkHint') }}</div>
          </q-card-section>
        </q-card>

        <q-card flat bordered>
          <q-card-section class="actions-row">
            <q-btn
              v-if="user.status === 'active'"
              color="negative"
              outline
              no-caps
              icon="block"
              :label="$t('users.detail.block')"
              @click="toggleBlock"
            />
            <q-btn
              v-else
              color="positive"
              outline
              no-caps
              icon="check_circle"
              :label="$t('users.detail.unblock')"
              @click="toggleBlock"
            />
            <q-btn
              color="primary"
              outline
              no-caps
              icon="logout"
              :label="$t('users.detail.logoutAll')"
              @click="logoutAll"
            />
            <q-btn
              v-if="user.email"
              color="primary"
              outline
              no-caps
              icon="mail"
              :label="$t('users.detail.sendLink')"
              @click="sendLink"
            />
            <q-btn
              color="primary"
              unelevated
              no-caps
              icon="add_card"
              :label="$t('users.detail.grant')"
              @click="grantOpen = true"
            />
          </q-card-section>
        </q-card>

        <q-table
          flat
          bordered
          hide-pagination
          :rows-per-page-options="[0]"
          :title="$t('users.detail.subscriptions')"
          :rows="user.subscriptions"
          :columns="subscriptionColumns"
          row-key="id"
          :no-data-label="$t('common.noData')"
        />
        <q-table
          flat
          bordered
          hide-pagination
          :rows-per-page-options="[0]"
          :title="$t('users.detail.payments')"
          :rows="user.payments"
          :columns="paymentColumns"
          row-key="id"
          :no-data-label="$t('common.noData')"
        />
        <q-table
          flat
          bordered
          hide-pagination
          :rows-per-page-options="[0]"
          :title="$t('users.detail.sessions')"
          :rows="user.sessions"
          :columns="sessionColumns"
          row-key="id"
          :no-data-label="$t('common.noData')"
        />
        <q-table
          flat
          bordered
          hide-pagination
          :rows-per-page-options="[0]"
          :title="$t('users.detail.logins')"
          :rows="user.login_events"
          :columns="loginColumns"
          row-key="created_at"
          :no-data-label="$t('common.noData')"
        />
      </div>
    </StatusPanel>

    <q-dialog v-model="grantOpen">
      <q-card class="dialog-card">
        <q-form @submit.prevent="grant">
          <q-card-section class="text-h6">{{ $t('users.detail.grant') }}</q-card-section>
          <q-card-section class="q-gutter-md">
            <q-banner v-if="form.message.value" class="bg-negative text-white" rounded dense>{{
              form.message.value
            }}</q-banner>
            <q-select
              v-model="draft.plan_id"
              outlined
              emit-value
              map-options
              :options="planOptions"
              :label="$t('payments.plan')"
              :error="!!form.errors.plan_id"
              :error-message="form.errors.plan_id"
            />
            <q-input
              v-model="draft.starts_at"
              outlined
              type="datetime-local"
              stack-label
              :label="$t('users.startsAt')"
              :hint="$t('users.startsAtHint')"
            />
            <q-input
              v-model="draft.note"
              outlined
              type="textarea"
              autogrow
              :label="$t('users.note')"
            />
          </q-card-section>
          <q-card-actions align="left">
            <q-btn
              type="submit"
              color="primary"
              unelevated
              no-caps
              :label="$t('common.save')"
              :loading="form.loading.value"
            />
            <q-btn flat no-caps :label="$t('common.cancel')" @click="grantOpen = false" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Notify } from 'quasar'
import { useI18n } from 'vue-i18n'
import StatusPanel from '@/components/StatusPanel.vue'
import { useAdminUsersStore } from '@/stores/useAdminUsersStore'
import { useAdminPaymentsStore } from '@/stores/useAdminPaymentsStore'
import { useApiForm } from '@/composables/useApiForm'
import { useAsync } from '@/composables/useAsync'
import { useConfirm } from '@/composables/useConfirm'
import { formatDateTime } from '@shared/utils/date.js'
import { formatPrice } from '@shared/utils/number.js'

const route = useRoute()
const { t } = useI18n()
const users = useAdminUsersStore()
const paymentsStore = useAdminPaymentsStore()
const confirm = useConfirm()
const form = useApiForm()

const user = ref(null)
const plans = ref([])
const grantOpen = ref(false)
const draft = reactive({ plan_id: null, starts_at: '', note: '' })

const {
  loading,
  error,
  run: load,
} = useAsync(
  async () => {
    user.value = await users.get(route.params.id)
  },
  { fallbackMessage: t('common.genericError') },
)

const planOptions = computed(() =>
  plans.value.filter((p) => p.active).map((p) => ({ value: p.id, label: p.title })),
)

const dateField = (name) => (row) => (row[name] ? formatDateTime(row[name]) : '—')
const subscriptionColumns = computed(() => [
  { name: 'plan', label: t('payments.plan'), align: 'right', field: (r) => r.plan?.title },
  { name: 'from', label: t('users.detail.from'), align: 'right', field: dateField('starts_at') },
  { name: 'to', label: t('users.detail.to'), align: 'right', field: dateField('ends_at') },
  { name: 'note', label: t('users.note'), align: 'right', field: (r) => r.note || '—' },
])
const paymentColumns = computed(() => [
  { name: 'plan', label: t('payments.plan'), align: 'right', field: (r) => r.plan?.title },
  {
    name: 'amount',
    label: t('payments.amount'),
    align: 'right',
    field: (r) => formatPrice(r.amount, t(`currency.${r.currency}`)),
  },
  {
    name: 'status',
    label: t('payments.status'),
    align: 'right',
    field: (r) => t(`payments.statusValue.${r.status}`),
  },
  { name: 'date', label: t('payments.createdAt'), align: 'right', field: dateField('created_at') },
])
const sessionColumns = computed(() => [
  { name: 'browser', label: t('users.detail.browser'), align: 'right', field: 'browser' },
  { name: 'os', label: t('users.detail.os'), align: 'right', field: 'os' },
  { name: 'ip', label: t('users.detail.ip'), align: 'right', field: 'ip', classes: 'ltr' },
  {
    name: 'activity',
    label: t('users.detail.lastActivity'),
    align: 'right',
    field: dateField('last_activity_at'),
  },
])
const loginColumns = computed(() => [
  {
    name: 'method',
    label: t('users.detail.method'),
    align: 'right',
    field: (r) => t(`users.loginMethod.${r.method}`),
  },
  {
    name: 'result',
    label: t('users.detail.result'),
    align: 'right',
    field: (r) => (r.successful ? t('users.detail.ok') : t('users.detail.failed')),
  },
  { name: 'ip', label: t('users.detail.ip'), align: 'right', field: 'ip', classes: 'ltr' },
  { name: 'time', label: t('users.detail.time'), align: 'right', field: dateField('created_at') },
])

async function act(key, action) {
  const ok = await confirm({
    message: t(`users.confirm.${key}`, { name: user.value.name }),
    color: key === 'block' || key === 'logoutAll' ? 'negative' : 'primary',
  })
  if (!ok) return
  await action(user.value.id)
  Notify.create({ type: 'positive', message: t('users.done') })
  await load()
}

const toggleBlock = () =>
  user.value.status === 'active' ? act('block', users.block) : act('unblock', users.unblock)
const logoutAll = () => act('logoutAll', users.logoutAll)
const sendLink = () => act('sendLink', users.sendPasswordLink)

async function grant() {
  const ok = await form.submit(() =>
    users.grantSubscription(user.value.id, {
      plan_id: draft.plan_id,
      starts_at: draft.starts_at || undefined,
      note: draft.note || undefined,
    }),
  )
  if (!ok) return
  grantOpen.value = false
  Object.assign(draft, { plan_id: null, starts_at: '', note: '' })
  Notify.create({ type: 'positive', message: t('users.done') })
  await load()
}

onMounted(async () => {
  load()
  plans.value = await paymentsStore.plans().catch(() => [])
})
</script>

<style scoped>
.dialog-card {
  width: 480px;
  max-width: 95vw;
}
.payload {
  overflow-wrap: anywhere;
}
.column > * {
  min-width: 0;
  max-width: 100%;
}
</style>
