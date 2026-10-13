<template>
  <q-page class="app-page has-miniplayer">
    <PageHeader :title="''" :back="true" fallback="/" />

    <StatusPanel :loading="loading" :error="error" :retry="load">
      <template v-if="post">
        <div class="cover"><CoverImage :url="post.cover_url" radius="24px" /></div>
        <h1 class="title">{{ toPersianDigits(post.title) }}</h1>
        <div class="meta muted">
          <span>{{ meta }}</span>
          <span class="chip" :class="post.access === 'free' ? 'chip--free' : 'chip--premium'">
            {{ post.access === 'free' ? $t('post.free') : $t('post.premium') }}
          </span>
        </div>

        <q-btn
          v-if="post.has_audio && !post.locked"
          class="btn"
          color="primary"
          text-color="dark"
          unelevated
          no-caps
          :icon="playingThis ? 'pause' : 'play_arrow'"
          :label="playingThis ? $t('player.pause') : $t('post.playEpisode')"
          @click="onPlay"
        />

        <div v-if="post.sponsor_name" class="card sponsor">
          <div class="muted small">{{ $t('post.sponsor') }}</div>
          <div class="sponsor-name">{{ post.sponsor_name }}</div>
          <a
            v-if="post.sponsor_url"
            class="btn-link"
            :href="post.sponsor_url"
            target="_blank"
            rel="noopener noreferrer"
            dir="ltr"
          >
            {{ hostOf(post.sponsor_url) }}
          </a>
        </div>

        <!-- Preview for readers without access: excerpt + purchase prompt. No body is ever received. -->
        <template v-if="post.locked">
          <p class="excerpt">{{ post.excerpt }}…</p>
          <div class="card lock">
            <div class="lock-icon"><q-icon name="lock_outline" size="28px" /></div>
            <h2>{{ $t('post.lockedTitle') }}</h2>
            <p class="muted">{{ $t('post.lockedText') }}</p>
            <q-btn
              class="btn"
              color="primary"
              text-color="dark"
              unelevated
              no-caps
              :label="$t('post.buy')"
              :to="{ name: 'plans' }"
            />
          </div>
        </template>

        <template v-else>
          <iframe
            v-if="post.embed_url"
            class="video"
            :src="post.embed_url"
            allowfullscreen
            referrerpolicy="no-referrer"
            sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
            :title="post.title"
          />
          <!-- body is sanitised HTML from the API; this is the only place it is rendered -->
          <div v-if="post.body" class="prose" dir="rtl" v-html="post.body" />
        </template>

        <div v-if="post.tags?.length" class="tags">
          <router-link
            v-for="tag in post.tags"
            :key="tag"
            class="chip tag"
            :to="{ name: 'search', query: { tag } }"
            >#{{ tag }}</router-link
          >
        </div>
      </template>
    </StatusPanel>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import CoverImage from '@/components/CoverImage.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { usePlayerStore } from '@/stores/usePlayerStore'
import { usePostsStore } from '@/stores/usePostsStore'
import { formatDate } from '@shared/utils/date.js'
import { formatDuration, toPersianDigits } from '@shared/utils/number.js'

const route = useRoute()
const { t } = useI18n()
const posts = usePostsStore()
const player = usePlayerStore()
const post = ref(null)
const loading = ref(true)
const error = ref('')

const meta = computed(() =>
  [
    formatDate(post.value.published_at),
    post.value.audio_duration ? formatDuration(post.value.audio_duration) : null,
  ]
    .filter(Boolean)
    .join(' · '),
)
const playingThis = computed(() => player.current?.id === post.value?.id && player.isPlaying)

const hostOf = (url) => {
  try {
    return new URL(url).host
  } catch {
    return url
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    post.value = await posts.fetchPost(route.params.id)
  } catch (e) {
    error.value =
      e.response?.status === 404
        ? t('post.notFound')
        : e.response?.data?.message || t('common.genericError')
  } finally {
    loading.value = false
  }
}

function onPlay() {
  if (player.current?.id === post.value.id) player.toggle()
  else player.play(post.value)
}

onMounted(load)
watch(() => route.params.id, load)
</script>

<style scoped lang="scss">
.cover {
  width: 200px;
  height: 200px;
  margin: 8px auto 20px;
}

.title {
  font-size: 22px;
  line-height: 1.7;
  margin: 0 0 10px;
}

.meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  margin-bottom: 18px;
}

.sponsor {
  margin: 18px 0;
}

.sponsor-name {
  font-size: 16px;
  font-weight: 600;
  margin: 4px 0;
}

.small {
  font-size: 12px;
}

.excerpt {
  line-height: 2;
  margin: 18px 0;
}

.lock {
  text-align: center;
  border: 1px solid var(--purple);
  padding: 22px 16px;

  h2 {
    font-size: 17px;
    margin: 12px 0 6px;
  }

  p {
    margin: 0 0 16px;
  }
}

.lock-icon {
  width: 52px;
  height: 52px;
  margin: 0 auto;
  border-radius: 50%;
  background: var(--purple);
  display: grid;
  place-items: center;
}

.video {
  width: 100%;
  aspect-ratio: 16 / 9;
  border: 0;
  border-radius: 16px;
  margin: 16px 0;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 20px;
}

.tag {
  color: var(--primary);
  text-decoration: none;
}
</style>
