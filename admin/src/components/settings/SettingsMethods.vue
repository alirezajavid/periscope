<template>
  <div>
    <div class="toolbar-row">
      <p class="text-grey-8 q-ma-none">{{ $t('settings.method.note') }}</p>
      <q-space />
      <q-btn
        color="primary"
        unelevated
        no-caps
        icon="add"
        :label="$t('settings.method.new')"
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
            editingId ? $t('settings.method.edit') : $t('settings.method.new')
          }}</q-card-section>
          <q-card-section class="column q-gutter-md">
            <q-banner v-if="form.message.value" class="bg-negative text-white" rounded dense>{{
              form.message.value
            }}</q-banner>
            <q-select
              v-model="draft.type"
              outlined
              emit-value
              map-options
              :options="typeOptions"
              :label="$t('settings.method.type')"
            />
            <q-input
              v-model="draft.title"
              outlined
              :label="$t('settings.method.title')"
              :error="!!form.errors.title"
              :error-message="form.errors.title"
            />
            <q-select
              v-model="draft.currency"
              outlined
              emit-value
              map-options
              :options="currencyOptions"
              :label="$t('settings.method.currency')"
            />
            <q-input
              v-model="draft.instructions"
              outlined
              type="textarea"
              autogrow
              :label="$t('settings.method.instructions')"
              :error="!!form.errors.instructions"
              :error-message="form.errors.instructions"
            />
            <q-input
              v-model.number="draft.sort_order"
              outlined
              type="number"
              min="0"
              :label="$t('settings.method.order')"
            />
            <q-toggle v-model="draft.active" :label="$t('settings.method.active')" />
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

const { t } = useI18n()
const store = useAdminPaymentsStore()
const form = useApiForm()

const rows = ref([])
const loading = ref(false)
const dialogOpen = ref(false)
const editingId = ref(null)
const draft = reactive({
  type: 'card',
  title: '',
  instructions: '',
  currency: 'IRT',
  sort_order: 0,
  active: true,
})

const typeOptions = computed(() =>
  ['card', 'paypal', 'crypto'].map((value) => ({
    value,
    label: t(`settings.method.typeValue.${value}`),
  })),
)
const currencyOptions = computed(() =>
  ['IRT', 'USD', 'USDT'].map((value) => ({ value, label: t(`currency.${value}`) })),
)

const columns = computed(() => [
  { name: 'title', label: t('settings.method.title'), align: 'right', field: 'title' },
  {
    name: 'type',
    label: t('settings.method.type'),
    align: 'right',
    field: (r) => t(`settings.method.typeValue.${r.type}`),
  },
  {
    name: 'currency',
    label: t('settings.method.currency'),
    align: 'right',
    field: (r) => t(`currency.${r.currency}`),
  },
  { name: 'active', label: t('settings.method.active'), align: 'right' },
  { name: 'actions', label: '', align: 'left' },
])

async function load() {
  loading.value = true
  try {
    rows.value = await store.methods()
  } finally {
    loading.value = false
  }
}

function open(method) {
  editingId.value = method?.id ?? null
  Object.assign(draft, {
    type: method?.type ?? 'card',
    title: method?.title ?? '',
    instructions: method?.instructions ?? '',
    currency: method?.currency ?? 'IRT',
    sort_order: method?.sort_order ?? 0,
    active: method?.active ?? true,
  })
  dialogOpen.value = true
}

async function submit() {
  const ok = await form.submit(() => store.saveMethod(editingId.value, { ...draft }))
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
</style>
