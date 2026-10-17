<template>
  <q-page class="page">
    <div class="toolbar-row">
      <h1 class="page-title q-ma-none">{{ $t('posts.title') }}</h1>
      <q-space />
      <q-btn
        color="primary"
        unelevated
        no-caps
        icon="add"
        :label="$t('posts.new')"
        :to="{ name: 'post-new' }"
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
        :label="$t('posts.status')"
        style="min-width: 160px"
        @update:model-value="reload"
      />
      <q-select
        v-model="filters.access"
        dense
        outlined
        clearable
        emit-value
        map-options
        :options="accessOptions"
        :label="$t('posts.access')"
        style="min-width: 160px"
        @update:model-value="reload"
      />
      <q-toggle
        v-model="filters.trashed"
        :label="$t('posts.trashed')"
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
      <template #body-cell-title="props">
        <q-td :props="props">
          <router-link
            class="text-primary"
            :to="{ name: 'post-edit', params: { id: props.row.id } }"
          >
            {{ props.row.title }}
          </router-link>
          <div v-if="props.row.deleted_at" class="text-negative text-caption">
            {{ $t('posts.deleted') }}
          </div>
        </q-td>
      </template>
      <template #body-cell-access="props">
        <q-td :props="props">
          <q-badge
            :color="props.row.access === 'free' ? 'positive' : 'secondary'"
            :label="$t(`posts.accessValue.${props.row.access}`)"
          />
        </q-td>
      </template>
      <template #body-cell-status="props">
        <q-td :props="props">
          <q-badge
            :color="props.row.status === 'published' ? 'primary' : 'grey'"
            :label="$t(`posts.statusValue.${props.row.status}`)"
          />
        </q-td>
      </template>
      <template #body-cell-published_at="props">
        <q-td :props="props">{{
          props.row.published_at ? formatDateTime(props.row.published_at) : '—'
        }}</q-td>
      </template>
      <template #body-cell-watermarks="props">
        <q-td :props="props">
          <template v-if="props.row.has_audio && props.row.access === 'premium'">
            <q-badge
              color="positive"
              :label="formatNumber(props.row.watermarks?.ready ?? 0)"
              class="q-mr-xs"
            >
              <q-tooltip>{{ $t('posts.wm.ready') }}</q-tooltip>
            </q-badge>
            <q-badge
              v-if="props.row.watermarks?.building"
              color="warning"
              text-color="dark"
              :label="formatNumber(props.row.watermarks.building)"
              class="q-mr-xs"
            >
              <q-tooltip>{{ $t('posts.wm.building') }}</q-tooltip>
            </q-badge>
            <q-badge
              v-if="props.row.watermarks?.failed"
              color="negative"
              :label="formatNumber(props.row.watermarks.failed)"
              class="cursor-pointer"
              @click="$router.push({ name: 'post-edit', params: { id: props.row.id } })"
            >
              <q-tooltip>{{ $t('posts.wm.failed') }}</q-tooltip>
            </q-badge>
          </template>
          <span v-else class="text-grey-6">—</span>
        </q-td>
      </template>
      <template #body-cell-actions="props">
        <q-td :props="props" class="text-left">
          <q-btn
            v-if="!props.row.deleted_at"
            flat
            round
            dense
            icon="edit"
            :to="{ name: 'post-edit', params: { id: props.row.id } }"
            :aria-label="$t('common.edit')"
          />
          <q-btn
            v-if="!props.row.deleted_at"
            flat
            round
            dense
            color="negative"
            icon="delete"
            :aria-label="$t('common.delete')"
            @click="askDelete(props.row)"
          />
          <q-btn
            v-else
            flat
            dense
            no-caps
            color="primary"
            icon="restore"
            :label="$t('posts.restore')"
            @click="doRestore(props.row)"
          />
        </q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Notify } from 'quasar'
import { useI18n } from 'vue-i18n'
import { useAdminPostsStore } from '@/stores/useAdminPostsStore'
import { useConfirm } from '@/composables/useConfirm'
import { formatDateTime } from '@shared/utils/date.js'
import { formatNumber, toPersianDigits } from '@shared/utils/number.js'

const { t } = useI18n()
const posts = useAdminPostsStore()
const confirm = useConfirm()

const rows = ref([])
const loading = ref(false)
const error = ref('')
const filters = reactive({ q: '', status: null, access: null, trashed: false })
const pagination = ref({
  page: 1,
  rowsPerPage: 20,
  rowsNumber: 0,
  sortBy: 'id',
  descending: true,
})
let timer = null

const statusOptions = computed(() =>
  ['draft', 'published'].map((value) => ({ value, label: t(`posts.statusValue.${value}`) })),
)
const accessOptions = computed(() =>
  ['free', 'premium'].map((value) => ({ value, label: t(`posts.accessValue.${value}`) })),
)

// Sorting happens on the server (the list is paginated), so `name` is the API's sort key.
const countColumn = (name, key, label) => ({
  name,
  label,
  field: (row) => row.feedback?.[key] ?? 0,
  align: 'right',
  sortable: true,
  format: (v) => formatNumber(v),
})
const columns = computed(() => [
  {
    name: 'id',
    label: '#',
    field: 'id',
    align: 'right',
    sortable: true,
    format: (v) => formatNumber(v),
  },
  {
    name: 'episode_number',
    label: t('posts.episodeNumber'),
    field: 'episode_number',
    align: 'right',
    sortable: true,
    format: (v) => (v == null ? '—' : formatNumber(v)),
  },
  {
    name: 'title',
    label: t('posts.titleField'),
    field: 'title',
    align: 'right',
    sortable: true,
    format: (v) => toPersianDigits(v),
  },
  { name: 'access', label: t('posts.access'), align: 'right', sortable: true },
  { name: 'status', label: t('posts.status'), align: 'right', sortable: true },
  { name: 'published_at', label: t('posts.publishedAt'), align: 'right', sortable: true },
  countColumn('likes_count', 'likes', t('posts.likes')),
  countColumn('dislikes_count', 'dislikes', t('posts.dislikes')),
  countColumn('comments_count', 'comments', t('posts.comments')),
  { name: 'watermarks', label: t('posts.watermark'), align: 'right' },
  { name: 'actions', label: '', align: 'left' },
])

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = {
      page,
      q: filters.q || undefined,
      status: filters.status || undefined,
      access: filters.access || undefined,
      trashed: filters.trashed ? 1 : undefined,
      sort: pagination.value.sortBy || undefined,
      descending: pagination.value.descending ? 1 : 0,
    }
    const data = await posts.list(params)
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
const onRequest = ({ pagination: p }) => {
  pagination.value = { ...pagination.value, sortBy: p.sortBy, descending: p.descending }
  load(p.page)
}

async function askDelete(post) {
  const ok = await confirm({
    message: t('posts.deleteConfirm', { title: post.title }),
    label: t('common.delete'),
  })
  if (!ok) return
  await posts.remove(post.id)
  Notify.create({ type: 'positive', message: t('common.saved') })
  load(pagination.value.page)
}

async function doRestore(post) {
  await posts.restore(post.id)
  Notify.create({ type: 'positive', message: t('common.saved') })
  load(pagination.value.page)
}

onMounted(reload)
</script>
