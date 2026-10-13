<template>
  <q-page class="page">
    <div class="toolbar-row">
      <q-btn
        flat
        round
        icon="arrow_forward"
        :aria-label="$t('common.back')"
        :to="{ name: 'posts' }"
      />
      <h1 class="page-title q-ma-none">{{ isNew ? $t('posts.new') : $t('posts.edit') }}</h1>
    </div>

    <StatusPanel :loading="loadingPost" :error="loadError" :retry="load">
      <q-banner v-if="message" class="bg-negative text-white q-mb-md" rounded dense>{{
        message
      }}</q-banner>
      <div class="row q-col-gutter-lg">
        <!-- Main column -->
        <div class="col-12 col-md-8">
          <q-card flat bordered class="q-pa-md">
            <q-input
              v-model="form.title"
              :label="$t('posts.titleField')"
              outlined
              :error="!!errors.title"
              :error-message="errors.title"
              class="q-mb-md"
            />
            <q-input
              v-model="form.excerpt"
              :label="$t('posts.excerpt')"
              :hint="$t('posts.excerptHint')"
              outlined
              type="textarea"
              autogrow
              :error="!!errors.excerpt"
              :error-message="errors.excerpt"
              class="q-mb-md"
            />

            <div class="text-subtitle2 q-mb-xs">{{ $t('posts.body') }}</div>
            <!-- Only the toolbar the API accepts (docs/reference.md §4): no images, no other formatting -->
            <q-editor
              v-model="form.body"
              min-height="14rem"
              paragraph-tag="p"
              :toolbar="toolbar"
              :definitions="definitions"
              dir="rtl"
            />
            <div v-if="errors.body" class="text-negative text-caption q-mt-xs">
              {{ errors.body }}
            </div>

            <q-input
              v-model="form.embed_code"
              :label="$t('posts.embed')"
              :hint="$t('posts.embedHint')"
              outlined
              type="textarea"
              autogrow
              dir="ltr"
              class="q-mt-md"
              :error="!!errors.embed_code"
              :error-message="errors.embed_code"
            />
          </q-card>

          <q-card v-if="!isNew && post?.has_audio" flat bordered class="q-pa-md q-mt-md">
            <div class="row items-center q-mb-sm">
              <div class="text-subtitle1">{{ $t('posts.wmTitle') }}</div>
              <q-space />
              <q-btn
                v-if="post.watermarks?.failed"
                color="primary"
                outline
                no-caps
                dense
                icon="refresh"
                :label="$t('posts.wmRetry')"
                @click="retry"
              />
            </div>
            <div class="row q-gutter-sm">
              <q-badge
                color="positive"
                :label="`${$t('posts.wm.ready')}: ${formatNumber(post.watermarks?.ready ?? 0)}`"
              />
              <q-badge
                color="warning"
                text-color="dark"
                :label="`${$t('posts.wm.building')}: ${formatNumber(post.watermarks?.building ?? 0)}`"
              />
              <q-badge
                color="negative"
                :label="`${$t('posts.wm.failedShort')}: ${formatNumber(post.watermarks?.failed ?? 0)}`"
              />
            </div>
            <q-list v-if="failed.length" bordered separator class="q-mt-md">
              <q-item v-for="f in failed" :key="f.id">
                <q-item-section>
                  <q-item-label
                    >{{ f.user.name }}
                    <span class="ltr text-grey-7">{{ f.user.email }}</span></q-item-label
                  >
                  <q-item-label caption class="ltr text-negative">{{ f.error }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-card>
        </div>

        <!-- Side column -->
        <div class="col-12 col-md-4">
          <q-card flat bordered class="q-pa-md">
            <q-select
              v-model="form.access"
              outlined
              emit-value
              map-options
              :options="accessOptions"
              :label="$t('posts.access')"
              class="q-mb-md"
            />
            <q-select
              v-model="form.status"
              outlined
              emit-value
              map-options
              :options="statusOptions"
              :label="$t('posts.status')"
              class="q-mb-md"
              :error="!!errors.status"
              :error-message="errors.status"
            />
            <q-input
              v-model="form.published_at"
              outlined
              type="datetime-local"
              dir="ltr"
              :label="$t('posts.publishedAt')"
              :hint="publishedHint"
              class="q-mb-md"
              :error="!!errors.published_at"
              :error-message="errors.published_at"
            />
            <q-input
              v-model.number="form.episode_number"
              outlined
              type="number"
              min="1"
              dir="ltr"
              :label="$t('posts.episodeNumber')"
              class="q-mb-md"
              :error="!!errors.episode_number"
              :error-message="errors.episode_number"
            />
            <q-select
              v-model="form.tags"
              outlined
              multiple
              use-input
              use-chips
              new-value-mode="add-unique"
              input-debounce="0"
              :options="tagOptions"
              :label="$t('posts.tags')"
              :hint="$t('posts.tagsHint')"
              @filter="filterTags"
            />
          </q-card>

          <q-card flat bordered class="q-pa-md q-mt-md">
            <div class="text-subtitle2 q-mb-sm">{{ $t('posts.sponsor') }}</div>
            <q-input
              v-model="form.sponsor_name"
              outlined
              dense
              :label="$t('posts.sponsorName')"
              class="q-mb-sm"
            />
            <q-input
              v-model="form.sponsor_url"
              outlined
              dense
              dir="ltr"
              :label="$t('posts.sponsorUrl')"
              :error="!!errors.sponsor_url"
              :error-message="errors.sponsor_url"
            />
          </q-card>

          <q-card flat bordered class="q-pa-md q-mt-md">
            <div class="text-subtitle2 q-mb-sm">{{ $t('posts.cover') }}</div>
            <img v-if="post?.cover_url" class="cover" :src="post.cover_url" alt="" />
            <q-file
              v-model="coverFile"
              outlined
              dense
              accept="image/jpeg,image/png,image/webp"
              :label="$t('posts.chooseCover')"
              :disable="isNew"
              :hint="isNew ? $t('posts.saveFirst') : $t('posts.coverHint')"
              @update:model-value="(f) => f && upload('cover', f)"
            />

            <div class="text-subtitle2 q-mt-md q-mb-sm">{{ $t('posts.audio') }}</div>
            <div v-if="post?.has_audio" class="text-caption q-mb-xs">
              {{
                $t('posts.audioStored', {
                  size: formatNumber(Math.round((post.audio_size || 0) / 1048576)),
                })
              }}
              <span v-if="post.audio_duration">· {{ formatDuration(post.audio_duration) }}</span>
            </div>
            <q-file
              v-model="audioFile"
              outlined
              dense
              accept="audio/*"
              :label="$t('posts.chooseAudio')"
              :disable="isNew"
              :hint="isNew ? $t('posts.saveFirst') : $t('posts.audioHint')"
              @update:model-value="(f) => f && upload('audio', f)"
            />
            <q-linear-progress v-if="uploading" :value="progress" class="q-mt-sm" rounded />
          </q-card>

          <div class="q-mt-md row q-gutter-sm">
            <q-btn
              color="primary"
              unelevated
              no-caps
              :label="$t('common.save')"
              :loading="saving"
              @click="save"
            />
            <q-btn
              v-if="!isNew && !post?.deleted_at"
              outline
              color="negative"
              no-caps
              icon="delete"
              :label="$t('common.delete')"
              @click="askDelete"
            />
          </div>
        </div>
      </div>
    </StatusPanel>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { useI18n } from 'vue-i18n'
import StatusPanel from '@/components/StatusPanel.vue'
import { useAdminPostsStore } from '@/stores/useAdminPostsStore'
import { useApiForm } from '@/composables/useApiForm'
import { useConfirm } from '@/composables/useConfirm'
import { formatDateTime } from '@shared/utils/date.js'
import { formatDuration, formatNumber } from '@shared/utils/number.js'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const posts = useAdminPostsStore()
const confirm = useConfirm()
const { loading: saving, errors, message, submit } = useApiForm()

const isNew = computed(() => !route.params.id)
const post = ref(null)
const loadingPost = ref(false)
const loadError = ref('')
const failed = ref([])
const form = reactive(blank())
const coverFile = ref(null)
const audioFile = ref(null)
const uploading = ref(false)
const progress = ref(0)
const allTags = ref([])
const tagOptions = ref([])

function blank() {
  return {
    title: '',
    excerpt: '',
    body: '',
    embed_code: '',
    access: 'premium',
    status: 'draft',
    published_at: '',
    episode_number: null,
    tags: [],
    sponsor_name: '',
    sponsor_url: '',
  }
}

// Toolbar limited to what the API's sanitiser keeps: bold, italic, h2, h3, lists, quote, link, clear formatting
const toolbar = [
  ['bold', 'italic'],
  ['h2', 'h3'],
  ['unordered', 'ordered'],
  ['quote'],
  ['link'],
  ['removeFormat'],
]
const definitions = {
  h2: { tip: 'h2', label: 'H2', cmd: 'formatBlock', param: 'H2' },
  h3: { tip: 'h3', label: 'H3', cmd: 'formatBlock', param: 'H3' },
}

const statusOptions = computed(() =>
  ['draft', 'published'].map((value) => ({ value, label: t(`posts.statusValue.${value}`) })),
)
const accessOptions = computed(() =>
  ['free', 'premium'].map((value) => ({ value, label: t(`posts.accessValue.${value}`) })),
)
const publishedHint = computed(() =>
  form.published_at ? formatDateTime(form.published_at) : t('posts.publishedHint'),
)

// ISO from the API ↔ value of <input type="datetime-local"> in the browser's timezone
const toLocalInput = (iso) => {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function fill(data) {
  post.value = data
  Object.assign(form, {
    title: data.title,
    excerpt: data.excerpt ?? '',
    body: data.body ?? '',
    embed_code: data.embed_code ?? '',
    access: data.access,
    status: data.status,
    published_at: toLocalInput(data.published_at),
    episode_number: data.episode_number,
    tags: data.tags ?? [],
    sponsor_name: data.sponsor_name ?? '',
    sponsor_url: data.sponsor_url ?? '',
  })
}

async function loadFailed() {
  if (post.value?.has_audio && post.value.watermarks?.failed)
    failed.value = await posts.watermarks(post.value.id, 'failed')
  else failed.value = []
}

async function load() {
  if (isNew.value) return
  loadingPost.value = true
  loadError.value = ''
  try {
    fill(await posts.get(route.params.id))
    await loadFailed()
  } catch (e) {
    loadError.value = e.response?.data?.message || t('common.genericError')
  } finally {
    loadingPost.value = false
  }
}

function payload() {
  return {
    ...form,
    excerpt: form.excerpt || null,
    body: form.body || null,
    embed_code: form.embed_code || null,
    sponsor_name: form.sponsor_name || null,
    sponsor_url: form.sponsor_url || null,
    episode_number: form.episode_number || null,
    published_at: form.published_at ? new Date(form.published_at).toISOString() : null,
  }
}

async function save() {
  let saved
  const ok = await submit(async () => (saved = await posts.save(post.value?.id, payload())))
  if (!ok) return
  Notify.create({ type: 'positive', message: t('common.saved') })
  if (isNew.value) router.replace({ name: 'post-edit', params: { id: saved.id } })
  else fill(saved)
}

async function upload(kind, file) {
  uploading.value = true
  progress.value = 0
  try {
    fill(await posts.upload(post.value.id, kind, file, (p) => (progress.value = p)))
    await loadFailed()
    Notify.create({ type: 'positive', message: t('common.saved') })
  } catch (e) {
    Notify.create({
      type: 'negative',
      message:
        e.response?.data?.errors?.[kind]?.[0] ||
        e.response?.data?.message ||
        t('common.genericError'),
    })
  } finally {
    uploading.value = false
    coverFile.value = null
    audioFile.value = null
  }
}

async function retry() {
  const queued = await posts.retryWatermarks(post.value.id)
  Notify.create({ type: 'positive', message: t('posts.wmQueued', { n: formatNumber(queued) }) })
}

async function askDelete() {
  if (
    !(await confirm({
      message: t('posts.deleteConfirm', { title: post.value.title }),
      label: t('common.delete'),
    }))
  )
    return
  await posts.remove(post.value.id)
  router.replace({ name: 'posts' })
}

function filterTags(value, update) {
  update(() => {
    const needle = value.toLowerCase()
    tagOptions.value = allTags.value.filter((tag) => tag.toLowerCase().includes(needle))
  })
}

onMounted(async () => {
  load()
  try {
    allTags.value = await posts.tags()
  } catch {
    allTags.value = []
  }
})
</script>

<style scoped>
.cover {
  display: block;
  width: 100%;
  max-width: 220px;
  border-radius: 8px;
  margin-bottom: 8px;
}
</style>
