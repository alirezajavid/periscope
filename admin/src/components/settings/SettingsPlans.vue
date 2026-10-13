<template>
  <div>
    <div class="toolbar-row">
      <p class="text-grey-8 q-ma-none">{{ $t('settings.plan.note') }}</p>
      <q-space />
      <q-btn
        color="primary"
        unelevated
        no-caps
        icon="add"
        :label="$t('settings.plan.new')"
        @click="open()"
      />
    </div>

    <q-table
      flat
      bordered
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      hide-pagination
      :rows-per-page-options="[0]"
      :no-data-label="$t('common.noData')"
    >
      <template #body-cell-active="props">
        <q-td :props="props">
          <q-badge
            :color="props.row.active ? 'positive' : 'grey'"
            :label="$t(props.row.active ? 'settings.yes' : 'settings.no')"
          />
        </q-td>
      </template>
      <template #body-cell-actions="props">
        <q-td :props="props" class="text-left">
          <q-btn
            flat
            round
            dense
            icon="edit"
            :aria-label="$t('common.edit')"
            @click="open(props.row)"
          />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialogOpen">
      <q-card class="dialog-card">
        <q-form @submit.prevent="submit">
          <q-card-section class="text-h6">{{
            editingId ? $t('settings.plan.edit') : $t('settings.plan.new')
          }}</q-card-section>
          <q-card-section class="column q-gutter-md">
            <q-banner v-if="form.message.value" class="bg-negative text-white" rounded dense>{{
              form.message.value
            }}</q-banner>
            <q-input
              v-model="draft.title"
              outlined
              :label="$t('settings.plan.title')"
              :error="!!form.errors.title"
              :error-message="form.errors.title"
            />
            <q-input
              v-model.number="draft.months"
              outlined
              type="number"
              min="1"
              max="36"
              :label="$t('settings.plan.months')"
              :error="!!form.errors.months"
              :error-message="form.errors.months"
            />
            <q-input
              v-model.number="draft.sort_order"
              outlined
              type="number"
              min="0"
              :label="$t('settings.plan.order')"
            />
            <q-toggle v-model="draft.active" :label="$t('settings.plan.active')" />

            <div class="text-subtitle2">{{ $t('settings.plan.prices') }}</div>
            <div v-for="price in draft.prices" :key="price.currency" class="price-row">
              <span>{{ $t(`currency.${price.currency}`) }}</span>
              <q-input
                v-model.number="price.amount"
                outlined
                dense
                dir="ltr"
                type="number"
                min="0"
                :label="$t('settings.plan.amount')"
              />
            </div>
            <div v-if="form.errors.prices" class="text-negative text-caption">
              {{ form.errors.prices }}
            </div>
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
            <q-btn flat no-caps :label="$t('common.cancel')" @click="dialogOpen = false" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Notify } from 'quasar'
import { useI18n } from 'vue-i18n'
import { useAdminPaymentsStore } from '@/stores/useAdminPaymentsStore'
import { useApiForm } from '@/composables/useApiForm'
import { formatNumber, formatPrice } from '@shared/utils/number.js'

const CURRENCIES = ['IRT', 'USD', 'USDT']

const { t } = useI18n()
const store = useAdminPaymentsStore()
const form = useApiForm()

const rows = ref([])
const loading = ref(false)
const dialogOpen = ref(false)
const editingId = ref(null)
const draft = reactive({ title: '', months: 3, sort_order: 0, active: true, prices: [] })

const columns = computed(() => [
  { name: 'title', label: t('settings.plan.title'), align: 'right', field: 'title' },
  {
    name: 'months',
    label: t('settings.plan.months'),
    align: 'right',
    field: 'months',
    format: formatNumber,
  },
  {
    name: 'prices',
    label: t('settings.plan.prices'),
    align: 'right',
    field: (r) =>
      r.prices.map((p) => formatPrice(p.amount, t(`currency.${p.currency}`))).join(' · '),
  },
  { name: 'active', label: t('settings.plan.active'), align: 'right' },
  { name: 'actions', label: '', align: 'left' },
])

async function load() {
  loading.value = true
  try {
    rows.value = await store.plans()
  } finally {
    loading.value = false
  }
}

function open(plan) {
  editingId.value = plan?.id ?? null
  const amounts = Object.fromEntries(
    (plan?.prices ?? []).map((p) => [p.currency, Number(p.amount)]),
  )
  Object.assign(draft, {
    title: plan?.title ?? '',
    months: plan?.months ?? 3,
    sort_order: plan?.sort_order ?? 0,
    active: plan?.active ?? true,
    prices: CURRENCIES.map((currency) => ({ currency, amount: amounts[currency] ?? null })),
  })
  dialogOpen.value = true
}

async function submit() {
  const prices = draft.prices.filter((p) => p.amount !== null && p.amount !== '')
  const ok = await form.submit(() => store.savePlan(editingId.value, { ...draft, prices }))
  if (!ok) return
  dialogOpen.value = false
  Notify.create({ type: 'positive', message: t('settings.saved') })
  load()
}

onMounted(load)
</script>

<style scoped>
.dialog-card {
  width: 480px;
  max-width: 95vw;
}
.price-row {
  display: grid;
  grid-template-columns: 80px 1fr;
  gap: 8px;
  align-items: center;
}
</style>
