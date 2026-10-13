<template>
  <q-page class="page">
    <h1 class="page-title">{{ $t('questions.title') }}</h1>

    <div class="toolbar-row">
      <q-btn-toggle
        v-model="status"
        unelevated
        no-caps
        clearable
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
      <template #body-cell-body="props">
        <q-td :props="props" class="question-cell">
          <div class="question-body">{{ props.row.body }}</div>
          <div v-if="props.row.answer" class="text-grey-8 q-mt-xs">
            <q-icon name="reply" /> {{ props.row.answer }}
          </div>
          <div v-if="props.row.answered_in_post" class="text-grey-8 q-mt-xs">
            {{ $t('questions.answeredIn') }}: {{ props.row.answered_in_post.title }}
          </div>
        </q-td>
      </template>
      <template #body-cell-status="props">
        <q-td :props="props">
          <q-badge
            :color="statusColor[props.row.status]"
            :label="$t(`questions.statusValue.${props.row.status}`)"
          />
        </q-td>
      </template>
      <template #body-cell-actions="props">
        <q-td :props="props" class="text-left">
          <q-btn
            v-if="props.row.status !== 'answered'"
            flat
            dense
            round
            :color="props.row.status === 'starred' ? 'warning' : 'grey-7'"
            :icon="props.row.status === 'starred' ? 'star' : 'star_border'"
            :aria-label="$t(props.row.status === 'starred' ? 'questions.unstar' : 'questions.star')"
            @click="toggleStar(props.row)"
          />
          <q-btn
            v-if="props.row.status !== 'answered'"
            flat
            dense
            no-caps
            color="primary"
            icon="reply"
            :label="$t('questions.answer')"
            @click="openAnswer(props.row)"
          />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialogOpen">
      <q-card v-if="current" class="dialog-card">
        <q-form @submit.prevent="submitAnswer">
          <q-card-section>
            <div class="text-h6">{{ $t('questions.answerTitle') }}</div>
            <p class="q-mt-sm q-mb-none">{{ current.body }}</p>
          </q-card-section>
          <q-card-section class="column q-gutter-md">
            <q-banner v-if="form.message.value" class="bg-negative text-white" rounded dense>{{
              form.message.value
            }}</q-banner>
            <q-input
              v-model="answerText"
              outlined
              type="textarea"
              autogrow
              :label="$t('questions.answerText')"
              :error="!!form.errors.answer"
              :error-message="form.errors.answer"
            />
            <q-select
              v-model="episode"
              outlined
              clearable
              use-input
              emit-value
              map-options
              input-debounce="300"
              :options="episodeOptions"
              :label="$t('questions.answeredIn')"
              @filter="searchEpisodes"
            />
          </q-card-section>
          <q-card-actions align="left">
            <q-btn
              type="submit"
              color="primary"
              unelevated
              no-caps
              :label="$t('questions.answer')"
              :loading="form.loading.value"
            />
            <q-btn flat no-caps :label="$t('common.cancel')" @click="dialogOpen = false" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useI18n } from 'vue-i18n'
import { useAdminCommunityStore } from '@/stores/useAdminCommunityStore'
import { useAdminPostsStore } from '@/stores/useAdminPostsStore'
import { useApiForm } from '@/composables/useApiForm'
import { useConfirm } from '@/composables/useConfirm'
import { formatDateTime } from '@shared/utils/date.js'

const { t } = useI18n()
const store = useAdminCommunityStore()
const posts = useAdminPostsStore()
const confirm = useConfirm()
const form = useApiForm()

const statusColor = { new: 'primary', starred: 'warning', answered: 'positive' }
const status = ref(null)
const rows = ref([])
const loading = ref(false)
const error = ref('')
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

const dialogOpen = ref(false)
const current = ref(null)
const answerText = ref('')
const episode = ref(null)
const episodeOptions = ref([])

const statusOptions = computed(() =>
  ['new', 'starred', 'answered'].map((value) => ({
    value,
    label: t(`questions.statusValue.${value}`),
  })),
)

const columns = computed(() => [
  { name: 'asker', label: t('questions.asker'), align: 'right', field: (r) => r.user?.name },
  { name: 'body', label: t('questions.text'), align: 'right' },
  { name: 'status', label: t('questions.status'), align: 'right' },
  {
    name: 'date',
    label: t('questions.date'),
    align: 'right',
    field: (r) => formatDateTime(r.created_at),
  },
  { name: 'actions', label: '', align: 'left' },
])

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const data = await store.questions({ page, status: status.value || undefined })
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

async function toggleStar(row) {
  await store.star(row.id)
  load(pagination.value.page)
}

function openAnswer(row) {
  current.value = row
  answerText.value = ''
  episode.value = null
  dialogOpen.value = true
}

async function searchEpisodes(input, update) {
  const list = await posts.list({ q: input || undefined, status: 'published' }).catch(() => null)
  update(() => {
    episodeOptions.value = (list?.data ?? []).map((p) => ({ value: p.id, label: p.title }))
  })
}

async function submitAnswer() {
  const ok = await confirm({
    message: t('questions.answerConfirm'),
    color: 'primary',
    label: t('questions.answer'),
  })
  if (!ok) return
  const done = await form.submit(() =>
    store.answer(current.value.id, {
      answer: answerText.value || undefined,
      answered_in_post_id: episode.value || undefined,
    }),
  )
  if (!done) return
  dialogOpen.value = false
  Notify.create({ type: 'positive', message: t('questions.answered') })
  load(pagination.value.page)
}

onMounted(reload)
</script>

<style scoped>
.dialog-card {
  width: 520px;
  max-width: 95vw;
}
.question-cell {
  white-space: normal;
  min-width: 220px;
  max-width: 480px;
}
.question-body {
  overflow-wrap: anywhere;
}
</style>
