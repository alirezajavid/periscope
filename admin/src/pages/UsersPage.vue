<template>
  <q-page class="page">
    <div class="toolbar-row">
      <h1 class="page-title q-ma-none">{{ $t('users.title') }}</h1>
      <q-space />
      <q-btn
        color="primary"
        unelevated
        no-caps
        icon="person_add"
        :label="$t('users.new')"
        @click="createOpen = true"
      />
    </div>

    <div class="toolbar-row">
      <q-input
        v-model="filters.q"
        dense
        outlined
        clearable
        :label="$t('common.search')"
        style="min-width: 220px"
        @update:model-value="reloadSoon"
      >
        <template #prepend><q-icon name="search" /></template>
      </q-input>
      <q-select
        v-model="filters.status"
        dense
        outlined
        clearable
        emit-value
        map-options
        :options="statusOptions"
        :label="$t('users.status')"
        style="min-width: 160px"
        @update:model-value="reload"
      />
      <q-select
        v-model="filters.subscribed"
        dense
        outlined
        clearable
        emit-value
        map-options
        :options="subscribedOptions"
        :label="$t('users.subscribed')"
        style="min-width: 160px"
        @update:model-value="reload"
      />
    </div>

    <q-banner v-if="error" class="bg-negative text-white q-mb-md" rounded dense>{{
      error
    }}</q-banner>

    <q-table
      v-model:pagination="pagination"
      flat
      bordered
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      :rows-per-page-options="[20]"
      :no-data-label="$t('common.noData')"
      @request="onRequest"
    >
      <template #body-cell-name="props">
        <q-td :props="props">
          <router-link class="text-primary" :to="{ name: 'user', params: { id: props.row.id } }">
            {{ props.row.name }}
          </router-link>
          <q-badge v-if="props.row.role === 'admin'" color="grey-7" class="q-mr-sm">{{
            $t('users.detail.admin')
          }}</q-badge>
        </q-td>
      </template>
      <template #body-cell-contact="props">
        <q-td :props="props"
          ><span class="ltr">{{ props.row.email || props.row.phone }}</span></q-td
        >
      </template>
      <template #body-cell-status="props">
        <q-td :props="props">
          <q-badge
            :color="props.row.status === 'active' ? 'positive' : 'negative'"
            :label="$t(`users.statusValue.${props.row.status}`)"
          />
        </q-td>
      </template>
      <template #body-cell-subscribed="props">
        <q-td :props="props">
          <q-badge
            :color="props.row.subscribed ? 'primary' : 'grey'"
            :label="$t(`users.subscribedValue.${props.row.subscribed ? 1 : 0}`)"
          />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="createOpen">
      <q-card class="dialog-card">
        <q-form @submit.prevent="create">
          <q-card-section>
            <div class="text-h6">{{ $t('users.new') }}</div>
            <p class="text-grey-8 q-mb-none">{{ $t('users.createHint') }}</p>
          </q-card-section>
          <q-card-section class="q-gutter-md">
            <q-banner v-if="form.message.value" class="bg-negative text-white" rounded dense>{{
              form.message.value
            }}</q-banner>
            <q-input
              v-model="draft.name"
              outlined
              :label="$t('users.name')"
              :error="!!form.errors.name"
              :error-message="form.errors.name"
            />
            <q-input
              v-model="draft.email"
              outlined
              dir="ltr"
              type="email"
              :label="$t('users.email')"
              :error="!!form.errors.email"
              :error-message="form.errors.email"
            />
            <q-input
              v-model="draft.phone"
              outlined
              dir="ltr"
              :label="$t('users.phone')"
              :hint="$t('users.phoneHint')"
              :error="!!form.errors.phone"
              :error-message="form.errors.phone"
            />
            <q-select
              v-model="draft.plan_id"
              outlined
              clearable
              emit-value
              map-options
              :options="planOptions"
              :label="$t('users.plan')"
            />
            <q-input
              v-if="draft.plan_id"
              v-model="draft.starts_at"
              outlined
              type="datetime-local"
              :label="$t('users.startsAt')"
              :hint="$t('users.startsAtHint')"
              stack-label
            />
            <q-input
              v-if="draft.plan_id"
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
            <q-btn flat no-caps :label="$t('common.cancel')" @click="createOpen = false" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Notify } from 'quasar'
import { useI18n } from 'vue-i18n'
import { useAdminUsersStore } from '@/stores/useAdminUsersStore'
import { useAdminPaymentsStore } from '@/stores/useAdminPaymentsStore'
import { useApiForm } from '@/composables/useApiForm'
import { formatDateTime } from '@shared/utils/date.js'
import { formatNumber } from '@shared/utils/number.js'

const { t } = useI18n()
const users = useAdminUsersStore()
const paymentsStore = useAdminPaymentsStore()
const form = useApiForm()

const rows = ref([])
const loading = ref(false)
const error = ref('')
const filters = reactive({ q: '', status: null, subscribed: null })
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })
const createOpen = ref(false)
const plans = ref([])
const draft = reactive({ name: '', email: '', phone: '', plan_id: null, starts_at: '', note: '' })
let timer = null

const statusOptions = computed(() =>
  ['active', 'blocked'].map((value) => ({ value, label: t(`users.statusValue.${value}`) })),
)
const subscribedOptions = computed(() =>
  [1, 0].map((value) => ({ value, label: t(`users.subscribedValue.${value}`) })),
)
const planOptions = computed(() =>
  plans.value.filter((p) => p.active).map((p) => ({ value: p.id, label: p.title })),
)

const columns = computed(() => [
  { name: 'id', label: '#', field: 'id', align: 'right', format: (v) => formatNumber(v) },
  { name: 'name', label: t('users.name'), field: 'name', align: 'right' },
  { name: 'contact', label: t('users.contact'), align: 'right' },
  { name: 'status', label: t('users.status'), align: 'right' },
  { name: 'subscribed', label: t('users.subscribed'), align: 'right' },
  {
    name: 'last_login_at',
    label: t('users.lastLogin'),
    align: 'right',
    field: 'last_login_at',
    format: (v) => (v ? formatDateTime(v) : t('users.never')),
  },
])

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const data = await users.list({
      page,
      q: filters.q || undefined,
      status: filters.status || undefined,
      subscribed: filters.subscribed ?? undefined,
    })
    rows.value = data.data
    pagination.value = {
      ...pagination.value,
      page: data.meta.current_page,
      rowsNumber: data.meta.total,
    }
  } catch (e) {
    error.value = e.response?.data?.message || t('common.genericError')
  } finally {
    loading.value = false
  }
}

const reload = () => load(1)
const reloadSoon = () => {
  clearTimeout(timer)
  timer = setTimeout(reload, 350)
}
const onRequest = ({ pagination: p }) => load(p.page)

async function create() {
  const ok = await form.submit(() =>
    users.create({
      name: draft.name,
      email: draft.email || undefined,
      phone: draft.phone || undefined,
      plan_id: draft.plan_id || undefined,
      starts_at: draft.plan_id && draft.starts_at ? draft.starts_at : undefined,
      note: draft.plan_id && draft.note ? draft.note : undefined,
    }),
  )
  if (!ok) return
  createOpen.value = false
  Object.assign(draft, { name: '', email: '', phone: '', plan_id: null, starts_at: '', note: '' })
  Notify.create({ type: 'positive', message: t('users.created') })
  reload()
}

onMounted(async () => {
  reload()
  plans.value = await paymentsStore.plans().catch(() => [])
})
</script>

<style scoped>
.dialog-card {
  width: 480px;
  max-width: 95vw;
}
</style>
