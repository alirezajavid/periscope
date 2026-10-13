<template>
  <q-page class="page">
    <h1 class="page-title">{{ $t('leaks.title') }}</h1>

    <div class="grid">
      <q-card flat bordered>
        <q-form @submit.prevent="runText">
          <q-card-section>
            <div class="text-subtitle1">{{ $t('leaks.text') }}</div>
            <p class="text-grey-8">{{ $t('leaks.textHint') }}</p>
            <q-input
              v-model="text"
              outlined
              type="textarea"
              rows="6"
              :error="!!textForm.errors.text"
              :error-message="textForm.errors.text"
            />
          </q-card-section>
          <q-card-actions>
            <q-btn
              type="submit"
              color="primary"
              unelevated
              no-caps
              :disable="!text"
              :label="$t('leaks.checkText')"
              :loading="textForm.loading.value"
            />
          </q-card-actions>
          <LeakResult v-if="textResult" :result="textResult" />
        </q-form>
      </q-card>

      <q-card flat bordered>
        <q-form @submit.prevent="runAudio">
          <q-card-section>
            <div class="text-subtitle1">{{ $t('leaks.audio') }}</div>
            <q-file
              v-model="audio"
              outlined
              accept="audio/*,.mp3,.wav,.m4a"
              :label="$t('leaks.pickAudio')"
              :error="!!audioForm.errors.audio"
              :error-message="audioForm.errors.audio"
              class="q-mt-md"
            />
            <q-banner
              v-if="audioForm.message.value"
              class="bg-negative text-white q-mt-md"
              rounded
              dense
              >{{ audioForm.message.value }}</q-banner
            >
          </q-card-section>
          <q-card-actions>
            <q-btn
              type="submit"
              color="primary"
              unelevated
              no-caps
              :disable="!audio"
              :label="$t('leaks.checkAudio')"
              :loading="audioForm.loading.value"
            />
          </q-card-actions>
          <LeakResult v-if="audioResult" :result="audioResult" />
        </q-form>
      </q-card>
    </div>

    <q-table
      v-model:pagination="pagination"
      class="q-mt-lg"
      flat
      bordered
      :title="$t('leaks.history')"
      :rows="rows"
      :columns="columns"
      row-key="id"
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
import LeakResult from '@/components/LeakResult.vue'
import { useAdminCommunityStore } from '@/stores/useAdminCommunityStore'
import { useApiForm } from '@/composables/useApiForm'
import { formatDateTime } from '@shared/utils/date.js'

const { t } = useI18n()
const store = useAdminCommunityStore()
const textForm = useApiForm()
const audioForm = useApiForm()

const text = ref('')
const audio = ref(null)
const textResult = ref(null)
const audioResult = ref(null)

const rows = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 30, rowsNumber: 0 })

const columns = computed(() => [
  {
    name: 'time',
    label: t('leaks.time'),
    align: 'right',
    field: 'created_at',
    format: (v) => formatDateTime(v),
  },
  {
    name: 'type',
    label: t('leaks.type'),
    align: 'right',
    field: 'type',
    format: (v) => t(`leaks.typeValue.${v}`),
  },
  { name: 'subject', label: t('leaks.subject'), align: 'right', field: 'subject' },
  {
    name: 'result',
    label: t('leaks.title'),
    align: 'right',
    field: 'result',
    format: (v) => t(`leaks.result.${v}`),
  },
  {
    name: 'user',
    label: t('leaks.foundUser'),
    align: 'right',
    field: (r) => r.found_user?.name ?? '—',
  },
  {
    name: 'admin',
    label: t('leaks.checkedBy'),
    align: 'right',
    field: (r) => r.admin?.name ?? '—',
  },
])

async function loadHistory(page = 1) {
  loading.value = true
  try {
    const data = await store.leakHistory(page)
    rows.value = data.data
    pagination.value = { ...pagination.value, page: data.current_page, rowsNumber: data.total }
  } finally {
    loading.value = false
  }
}
const onRequest = ({ pagination: p }) => loadHistory(p.page)

async function runText() {
  textResult.value = null
  const ok = await textForm.submit(async () => {
    textResult.value = await store.checkText(text.value)
  })
  if (ok) loadHistory()
}

async function runAudio() {
  audioResult.value = null
  const ok = await audioForm.submit(async () => {
    audioResult.value = await store.checkAudio(audio.value)
  })
  if (ok) loadHistory()
}

onMounted(loadHistory)
</script>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr));
  gap: 16px;
}
</style>
