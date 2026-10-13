<template>
  <q-page class="page">
    <h1 class="page-title">{{ $t('stats.title') }}</h1>

    <q-banner v-if="error" class="bg-negative text-white q-mb-md" rounded dense>{{
      error
    }}</q-banner>

    <q-table
      v-model:pagination="pagination"
      flat
      bordered
      :rows="rows"
      :columns="columns"
      row-key="post_id"
      :loading="loading"
      :rows-per-page-options="[30]"
      :no-data-label="$t('common.noData')"
      @request="onRequest"
    />
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAdminCommunityStore } from '@/stores/useAdminCommunityStore'
import { formatDuration, formatNumber, toPersianDigits } from '@shared/utils/number.js'

const { t } = useI18n()
const store = useAdminCommunityStore()

const rows = ref([])
const loading = ref(false)
const error = ref('')
const pagination = ref({ page: 1, rowsPerPage: 30, rowsNumber: 0 })

const columns = computed(() => [
  {
    name: 'title',
    label: t('stats.episode'),
    align: 'right',
    field: 'title',
    format: (v) => toPersianDigits(v),
    classes: 'title-cell',
  },
  {
    name: 'duration',
    label: t('stats.duration'),
    align: 'right',
    field: 'duration',
    format: (v) => (v ? formatDuration(v) : '—'),
  },
  {
    name: 'started',
    label: t('stats.started'),
    align: 'right',
    field: 'started',
    format: formatNumber,
  },
  {
    name: 'completed',
    label: t('stats.completed'),
    align: 'right',
    field: 'completed',
    format: formatNumber,
  },
  {
    name: 'percent',
    label: t('stats.percent'),
    align: 'right',
    field: 'completion_percent',
    format: (v) => `${formatNumber(v)}٪`,
  },
  {
    name: 'average',
    label: t('stats.average'),
    align: 'right',
    field: 'average_listened_seconds',
    format: (v) => formatDuration(v),
  },
])

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const data = await store.listeningStats(page)
    rows.value = data.data
    pagination.value = {
      ...pagination.value,
      page: data.current_page,
      rowsNumber: data.total,
    }
  } catch (e) {
    error.value = e.response?.data?.message || t('common.genericError')
  } finally {
    loading.value = false
  }
}

const onRequest = ({ pagination: p }) => load(p.page)

onMounted(load)
</script>
