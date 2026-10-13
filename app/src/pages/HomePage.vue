<template>
  <q-page class="app-page has-miniplayer">
    <header class="hero">
      <div class="hero-cover"><CoverImage radius="16px" /></div>
      <div class="hero-text">
        <h1>{{ $t('app.title') }}</h1>
        <div class="muted host">{{ $t('home.host') }}</div>
        <span v-if="subscription.active" class="chip chip--ok">
          {{ $t('home.subscriptionUntil', { date: formatDate(subscription.endsAt) }) }}
        </span>
        <router-link
          v-else-if="subscription.status"
          class="chip chip--premium sub-link"
          :to="{ name: 'plans' }"
        >
          {{ $t('home.getSubscription') }}
        </router-link>
      </div>
    </header>
    <p class="muted about">{{ $t('home.description') }}</p>

    <section v-if="resume" class="resume">
      <h2>{{ $t('home.continue') }}</h2>
      <div class="card resume-card" @click="openPost(resume.post)">
        <div class="resume-cover"><CoverImage :url="resume.post.cover_url" /></div>
        <div class="resume-body">
          <div class="resume-title">{{ toPersianDigits(resume.post.title) }}</div>
          <q-linear-progress
            rounded
            size="6px"
            :value="resumeRatio"
            color="primary"
            track-color="grey-9"
          />
          <div class="muted small" v-if="resume.remaining !== null">
            {{
              $t('home.remaining', {
                minutes: formatNumber(Math.max(1, Math.round(resume.remaining / 60))),
              })
            }}
          </div>
        </div>
        <q-btn
          round
          unelevated
          color="primary"
          text-color="dark"
          icon="play_arrow"
          :aria-label="$t('post.play')"
          @click.stop="playResume"
        />
      </div>
    </section>

    <section v-if="offlineItems.length" class="stored">
      <h2>{{ $t('home.stored') }}</h2>
      <EpisodeRow
        v-for="item in offlineItems"
        :key="item.id"
        :post="{ ...item, has_audio: true, locked: false }"
      />
    </section>

    <section>
      <div class="list-head">
        <h2>{{ $t('home.all') }}</h2>
        <span v-if="posts.total" class="muted small">{{
          $t('home.count', { count: formatNumber(posts.total) })
        }}</span>
      </div>

      <StatusPanel
        :loading="posts.loading && !posts.items.length"
        :error="!posts.items.length ? posts.error : ''"
        :empty="!posts.loading && !posts.items.length && !posts.error"
        :retry="() => posts.load()"
      >
        <q-infinite-scroll :offset="250" :disable="!posts.hasMore()" @load="onLoadMore">
          <EpisodeRow v-for="post in posts.items" :key="post.id" :post="post" />
          <template #loading>
            <div class="loading-more"><q-spinner color="primary" size="24px" /></div>
          </template>
        </q-infinite-scroll>
      </StatusPanel>
    </section>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import CoverImage from '@/components/CoverImage.vue'
import EpisodeRow from '@/components/EpisodeRow.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { usePlayerStore } from '@/stores/usePlayerStore'
import { usePostsStore } from '@/stores/usePostsStore'
import { useSubscriptionStore } from '@/stores/useSubscriptionStore'
import { formatDate } from '@shared/utils/date.js'
import { storedEpisodes } from '@/services/offlineAudio'
import { formatNumber, toPersianDigits } from '@shared/utils/number.js'

const router = useRouter()
const posts = usePostsStore()
const player = usePlayerStore()
const subscription = useSubscriptionStore()
const resume = ref(null)
const offlineItems = ref([])

const resumeRatio = computed(() => {
  const duration = resume.value?.post.audio_duration
  return duration ? Math.min(1, resume.value.position / duration) : 0
})

function openPost(post) {
  router.push({ name: 'post', params: { id: post.id } })
}

function playResume() {
  player.play(resume.value.post)
}

async function onLoadMore(_index, done) {
  await posts.loadMore()
  done(!posts.hasMore())
}

onMounted(async () => {
  await posts.load()
  // The API is unreachable (offline): offer what is stored on this device
  offlineItems.value = posts.error || !navigator.onLine ? storedEpisodes() : []
  if (!navigator.onLine) return
  subscription.fetchStatus().catch(() => {})
  try {
    resume.value = (await posts.fetchContinue())[0] ?? null
  } catch {
    resume.value = null
  }
})
</script>

<style scoped lang="scss">
.hero {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding-top: 24px;
}

.hero-cover {
  flex: none;
  width: 104px;
  height: 104px;
}

.hero-text {
  flex: 1;
  min-width: 0;

  h1 {
    font-size: 22px;
    margin: 8px 0 4px;
  }
}

.host {
  margin-bottom: 10px;
}

.sub-link {
  text-decoration: none;
  color: #fff;
}

.about {
  line-height: 2;
  margin: 14px 0 22px;
}

h2 {
  font-size: 18px;
  margin: 0 0 12px;
}

.resume {
  margin-bottom: 26px;
}

.resume-card {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
}

.resume-cover {
  flex: none;
  width: 56px;
  height: 56px;
}

.resume-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.resume-title {
  font-weight: 600;
}

.list-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-top: 8px;
}

.small {
  font-size: 12px;
}

.loading-more {
  display: flex;
  justify-content: center;
  padding: 16px;
}
</style>
