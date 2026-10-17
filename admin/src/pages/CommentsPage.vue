<template>
  <q-page class="page">
    <h1 class="page-title">{{ $t('comments.title') }}</h1>

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
      :rows-per-page-options="[30]"
      :no-data-label="$t('common.noData')"
      @request="onRequest"
    >
      <template #body-cell-author="props">
        <q-td :props="props">
          <div>{{ props.row.user?.name }}</div>
          <div class="text-grey-7 text-caption" dir="ltr">{{ props.row.user?.email }}</div>
          <q-badge
            v-if="props.row.author?.is_admin"
            color="primary"
            :label="$t('comments.admin')"
          />
        </q-td>
      </template>
      <template #body-cell-body="props">
        <q-td :props="props" class="comment-cell">
          <div v-if="props.row.parent_id" class="text-grey-7 text-caption">
            <q-icon name="subdirectory_arrow_left" /> {{ $t('comments.isReply') }}
          </div>
          <div class="comment-body">{{ props.row.body }}</div>
        </q-td>
      </template>
      <template #body-cell-post="props">
        <q-td :props="props" class="post-cell">
          <router-link
            v-if="props.row.post"
            :to="{ name: 'post-edit', params: { id: props.row.post.id } }"
            >{{ props.row.post.title }}</router-link
          >
        </q-td>
      </template>
      <template #body-cell-status="props">
        <q-td :props="props">
          <q-badge
            :color="props.row.hidden ? 'grey-7' : 'positive'"
            :label="$t(props.row.hidden ? 'comments.hidden' : 'comments.shown')"
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
            icon="reply"
            :label="$t('comments.reply')"
            @click="openReply(props.row)"
          />
          <q-btn
            v-if="!props.row.hidden"
            flat
            dense
            no-caps
            color="negative"
            icon="visibility_off"
            :label="$t('comments.hide')"
            @click="hide(props.row)"
          />
          <q-btn
            v-else
            flat
            dense
            no-caps
            color="grey-8"
            icon="visibility"
            :label="$t('comments.unhide')"
            @click="unhide(props.row)"
          />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialogOpen">
      <q-card v-if="current" class="dialog-card">
        <q-form @submit.prevent="submitReply">
          <q-card-section>
            <div class="text-h6">{{ $t('comments.replyTitle') }}</div>
            <p class="q-mt-sm q-mb-none comment-body">{{ current.body }}</p>
          </q-card-section>
          <q-card-section>
            <q-banner
              v-if="form.message.value"
              class="bg-negative text-white q-mb-md"
              rounded
              dense
              >{{ form.message.value }}</q-banner
            >
            <q-input
              v-model="replyText"
              outlined
              type="textarea"
              autogrow
              maxlength="2000"
              :label="$t('comments.replyText')"
              :hint="$t('comments.replyHint')"
              :error="!!form.errors.body"
              :error-message="form.errors.body"
            />
          </q-card-section>
          <q-card-actions align="left">
            <q-btn
              type="submit"
              color="primary"
              unelevated
              no-caps
              :label="$t('comments.send')"
              :loading="form.loading.value"
              :disable="replyText.trim().length < 2"
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
import { useApiForm } from '@/composables/useApiForm'
import { useConfirm } from '@/composables/useConfirm'
import { formatDateTime } from '@shared/utils/date.js'

const { t } = useI18n()
const store = useAdminCommunityStore()
const confirm = useConfirm()
const form = useApiForm()

const status = ref(null)
const rows = ref([])
const loading = ref(false)
const error = ref('')
const pagination = ref({ page: 1, rowsPerPage: 30, rowsNumber: 0 })

const dialogOpen = ref(false)
const current = ref(null)
const replyText = ref('')

const statusOptions = computed(() => [
  { value: 'shown', label: t('comments.shown') },
  { value: 'hidden', label: t('comments.hidden') },
])

const columns = computed(() => [
  { name: 'author', label: t('comments.author'), align: 'right' },
  { name: 'body', label: t('comments.text'), align: 'right' },
  { name: 'post', label: t('comments.post'), align: 'right' },
  { name: 'status', label: t('comments.status'), align: 'right' },
  {
    name: 'date',
    label: t('comments.date'),
    align: 'right',
    field: (r) => formatDateTime(r.created_at),
  },
  { name: 'actions', label: '', align: 'left' },
])

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const data = await store.comments({ page, status: status.value || undefined })
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

async function hide(row) {
  const ok = await confirm({
    message: t(row.parent_id ? 'comments.hideReplyConfirm' : 'comments.hideConfirm'),
    label: t('comments.hide'),
  })
  if (!ok) return
  await store.hideComment(row.id)
  load(pagination.value.page)
}

async function unhide(row) {
  await store.unhideComment(row.id)
  load(pagination.value.page)
}

function openReply(row) {
  current.value = row
  replyText.value = ''
  dialogOpen.value = true
}

async function submitReply() {
  const done = await form.submit(() =>
    store.replyToComment(current.value.id, replyText.value.trim()),
  )
  if (!done) return
  dialogOpen.value = false
  Notify.create({ type: 'positive', message: t('comments.replied') })
  load(pagination.value.page)
}

onMounted(reload)
</script>

<style scoped>
.dialog-card {
  width: 520px;
  max-width: 95vw;
}
.comment-cell {
  white-space: normal;
  min-width: 220px;
  max-width: 480px;
}
.post-cell {
  white-space: normal;
  max-width: 220px;
}
.comment-body {
  overflow-wrap: anywhere;
  white-space: pre-line;
}
</style>
