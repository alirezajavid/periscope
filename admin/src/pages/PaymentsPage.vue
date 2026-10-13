<template>
  <q-page class="page">
    <h1 class="page-title">{{ $t('payments.title') }}</h1>

    <div class="toolbar-row">
      <q-btn-toggle
        v-model="status"
        unelevated
        no-caps
        toggle-color="primary"
        color="white"
        text-color="primary"
        :options="statusOptions"
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
      <template #body-cell-status="props">
        <q-td :props="props">
          <q-badge
            :color="statusColor[props.row.status]"
            :text-color="props.row.status === 'pending' ? 'dark' : 'white'"
            :label="$t(`payments.statusValue.${props.row.status}`)"
          />
        </q-td>
      </template>
      <template #body-cell-actions="props">
        <q-td :props="props" class="text-left">
          <q-btn
            flat
            dense
            no-caps
            color="primary"
            icon="visibility"
            :label="$t('payments.review')"
            @click="open(props.row)"
          />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialogOpen" @hide="closeDialog">
      <q-card v-if="current" class="dialog-card">
        <q-card-section class="row items-center">
          <div class="text-h6">{{ current.user?.name }}</div>
          <q-space />
          <q-btn flat round dense icon="close" v-close-popup :aria-label="$t('common.cancel')" />
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-list dense>
            <q-item>
              <q-item-section>{{ $t('payments.plan') }}</q-item-section>
              <q-item-section side>{{ current.plan?.title }}</q-item-section>
            </q-item>
            <q-item>
              <q-item-section>{{ $t('payments.method') }}</q-item-section>
              <q-item-section side>{{ current.method?.title }}</q-item-section>
            </q-item>
            <q-item>
              <q-item-section>{{ $t('payments.amount') }}</q-item-section>
              <q-item-section side>{{ amountText(current) }}</q-item-section>
            </q-item>
            <q-item>
              <q-item-section>{{ $t('payments.trackingCode') }}</q-item-section>
              <q-item-section side class="ltr">{{ current.tracking_code }}</q-item-section>
            </q-item>
            <q-item v-if="current.reject_reason">
              <q-item-section>{{ $t('payments.reason') }}</q-item-section>
              <q-item-section side>{{ current.reject_reason }}</q-item-section>
            </q-item>
          </q-list>

          <div class="receipt q-mt-md">
            <q-spinner v-if="receiptLoading" color="primary" size="28px" />
            <img v-else-if="receipt" :src="receipt" alt="" />
            <span v-else class="text-negative">{{ $t('payments.receiptFailed') }}</span>
          </div>
        </q-card-section>

        <q-card-section v-if="current.status === 'pending'" class="q-gutter-md">
          <q-banner v-if="form.message.value" class="bg-negative text-white" rounded dense>{{
            form.message.value
          }}</q-banner>
          <q-input
            v-model="startsAt"
            outlined
            type="datetime-local"
            stack-label
            :label="$t('payments.startsAt')"
            :hint="$t('payments.startsAtHint')"
            :error="!!form.errors.starts_at"
            :error-message="form.errors.starts_at"
          />
          <q-input
            v-model="reason"
            outlined
            type="textarea"
            autogrow
            :label="$t('payments.reason')"
            :error="!!form.errors.reason"
            :error-message="form.errors.reason"
          />
        </q-card-section>

        <q-card-actions v-if="current.status === 'pending'" align="left">
          <q-btn
            color="positive"
            unelevated
            no-caps
            icon="check"
            :label="$t('payments.approve')"
            :loading="form.loading.value"
            @click="approve"
          />
          <q-btn
            color="negative"
            outline
            no-caps
            icon="close"
            :label="$t('payments.reject')"
            :loading="form.loading.value"
            @click="reject"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useI18n } from 'vue-i18n'
import { useAdminPaymentsStore } from '@/stores/useAdminPaymentsStore'
import { useApiForm } from '@/composables/useApiForm'
import { useConfirm } from '@/composables/useConfirm'
import { formatDateTime } from '@shared/utils/date.js'
import { formatPrice } from '@shared/utils/number.js'

const { t } = useI18n()
const store = useAdminPaymentsStore()
const confirm = useConfirm()
const form = useApiForm()

const statusColor = { pending: 'warning', approved: 'positive', rejected: 'negative' }
const status = ref('pending')
const rows = ref([])
const loading = ref(false)
const error = ref('')
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

const dialogOpen = ref(false)
const current = ref(null)
const receipt = ref('')
const receiptLoading = ref(false)
const startsAt = ref('')
const reason = ref('')

const statusOptions = computed(() =>
  ['pending', 'approved', 'rejected'].map((value) => ({
    value,
    label: t(`payments.statusValue.${value}`),
  })),
)

const amountText = (row) => formatPrice(row.amount, t(`currency.${row.currency}`))

const columns = computed(() => [
  { name: 'user', label: t('payments.user'), align: 'right', field: (r) => r.user?.name },
  { name: 'plan', label: t('payments.plan'), align: 'right', field: (r) => r.plan?.title },
  { name: 'method', label: t('payments.method'), align: 'right', field: (r) => r.method?.title },
  { name: 'amount', label: t('payments.amount'), align: 'right', field: amountText },
  {
    name: 'tracking',
    label: t('payments.trackingCode'),
    align: 'right',
    field: 'tracking_code',
    classes: 'ltr',
  },
  { name: 'status', label: t('payments.status'), align: 'right' },
  {
    name: 'created',
    label: t('payments.createdAt'),
    align: 'right',
    field: (r) => formatDateTime(r.created_at),
  },
  { name: 'actions', label: '', align: 'left' },
])

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const data = await store.list({ page, status: status.value })
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
const onRequest = ({ pagination: p }) => load(p.page)

async function open(row) {
  current.value = row
  startsAt.value = ''
  reason.value = ''
  receipt.value = ''
  dialogOpen.value = true
  receiptLoading.value = true
  try {
    receipt.value = await store.receiptUrl(row.id)
  } catch {
    receipt.value = ''
  } finally {
    receiptLoading.value = false
  }
}

function closeDialog() {
  if (receipt.value) URL.revokeObjectURL(receipt.value)
  receipt.value = ''
  current.value = null
}

async function review(key, action, message) {
  const ok = await confirm({
    message,
    color: key === 'approved' ? 'positive' : 'negative',
    label: t(key === 'approved' ? 'payments.approve' : 'payments.reject'),
  })
  if (!ok) return
  const done = await form.submit(action)
  if (!done) return
  Notify.create({ type: 'positive', message: t(`payments.${key}`) })
  dialogOpen.value = false
  load(pagination.value.page)
}

const approve = () =>
  review(
    'approved',
    () => store.approve(current.value.id, startsAt.value),
    t('payments.approveConfirm', {
      plan: current.value.plan?.title,
      name: current.value.user?.name,
    }),
  )

const reject = () =>
  review(
    'rejected',
    () => store.reject(current.value.id, reason.value),
    t('payments.rejectConfirm', { name: current.value.user?.name }),
  )

onMounted(reload)
</script>

<style scoped>
.dialog-card {
  width: 520px;
  max-width: 95vw;
}
.receipt {
  text-align: center;
}
.receipt img {
  max-width: 100%;
  max-height: 50vh;
  border: 1px solid #ddd;
}
</style>
