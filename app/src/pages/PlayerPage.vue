<template>
  <div class="player">
    <header class="top">
      <q-btn
        flat
        round
        icon="expand_more"
        size="lg"
        :aria-label="$t('common.back')"
        @click="close"
      />
      <span class="muted">{{ $t('player.nowPlaying') }}</span>
      <q-btn
        flat
        round
        icon="format_list_bulleted"
        :aria-label="$t('player.episodes')"
        :to="{ name: 'home' }"
      />
    </header>

    <template v-if="player.hasTrack">
      <div class="art"><CoverImage :url="player.current.cover_url" radius="28px" /></div>
      <h1 class="title">{{ toPersianDigits(player.current.title) }}</h1>
      <div class="muted show">{{ $t('app.title') }}</div>

      <div
        v-if="message"
        class="notice"
        :class="{ error: player.status === 'error' }"
        role="status"
      >
        <q-spinner
          v-if="player.status === 'preparing' || player.status === 'loading'"
          size="18px"
        />
        <span>{{ message }}</span>
        <q-btn
          v-if="player.status === 'error'"
          flat
          dense
          no-caps
          color="primary"
          :label="$t('common.retry')"
          @click="retry"
        />
        <q-btn
          v-if="player.error === 'subscription'"
          flat
          dense
          no-caps
          color="primary"
          :label="$t('post.buy')"
          :to="{ name: 'plans' }"
        />
      </div>

      <q-slider
        class="seek"
        :model-value="dragging ? dragValue : player.position"
        :min="0"
        :max="Math.max(player.duration, 1)"
        :step="1"
        color="primary"
        track-size="6px"
        thumb-size="16px"
        :disable="player.status !== 'playing' && player.status !== 'paused'"
        @update:model-value="onDrag"
        @change="onSeek"
      />
      <div class="times muted">
        <span>{{ formatDuration(player.position) }}</span>
        <span>{{ formatDuration(player.duration) }}</span>
      </div>

      <div class="controls">
        <button
          class="skip"
          type="button"
          :aria-label="$t('player.back15')"
          @click="player.back15()"
        >
          <q-icon name="replay" size="40px" />
          <span>{{ $t('player.seconds', { n: formatNumber(15) }) }}</span>
        </button>
        <q-btn
          class="main"
          round
          unelevated
          color="primary"
          text-color="dark"
          :icon="player.isPlaying ? 'pause' : 'play_arrow'"
          :loading="player.status === 'loading' || player.status === 'preparing'"
          :aria-label="player.isPlaying ? $t('player.pause') : $t('player.play')"
          @click="player.toggle()"
        />
        <button
          class="skip"
          type="button"
          :aria-label="$t('player.forward15')"
          @click="player.forward15()"
        >
          <q-icon name="refresh" size="40px" />
          <span>{{ $t('player.seconds', { n: formatNumber(15) }) }}</span>
        </button>
      </div>

      <div class="chips">
        <button class="pill" type="button" @click="sleepOpen = true">
          <q-icon name="bedtime" size="20px" />
          {{ sleepLabel }}
        </button>
        <button class="pill" type="button" @click="speedOpen = true">
          <q-icon name="speed" size="20px" />
          {{ formatSpeed(player.speed) }}
        </button>
      </div>
    </template>

    <div v-else class="empty muted">{{ $t('player.nothing') }}</div>

    <q-dialog v-model="speedOpen" position="bottom">
      <q-card class="sheet">
        <div class="sheet-title">{{ $t('player.speed') }}</div>
        <q-list>
          <q-item v-for="s in SPEEDS" :key="s" clickable @click="pickSpeed(s)">
            <q-item-section>{{ formatSpeed(s) }}</q-item-section>
            <q-item-section v-if="player.speed === s" side
              ><q-icon name="check" color="primary"
            /></q-item-section>
          </q-item>
        </q-list>
      </q-card>
    </q-dialog>

    <q-dialog v-model="sleepOpen" position="bottom">
      <q-card class="sheet">
        <div class="sheet-title">{{ $t('player.sleepTimer') }}</div>
        <q-list>
          <q-item clickable @click="pickSleep(null)">
            <q-item-section>{{ $t('player.sleepOff') }}</q-item-section>
            <q-item-section v-if="!player.sleepEndsAt" side
              ><q-icon name="check" color="primary"
            /></q-item-section>
          </q-item>
          <q-item v-for="m in SLEEP_OPTIONS" :key="m" clickable @click="pickSleep(m)">
            <q-item-section>{{ $t('player.sleepMinutes', { n: formatNumber(m) }) }}</q-item-section>
          </q-item>
        </q-list>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import CoverImage from '@/components/CoverImage.vue'
import { SLEEP_OPTIONS, SPEEDS, usePlayerStore } from '@/stores/usePlayerStore'
import { formatDuration, formatNumber, toPersianDigits } from '@shared/utils/number.js'

const router = useRouter()
const { t } = useI18n()
const player = usePlayerStore()
const speedOpen = ref(false)
const sleepOpen = ref(false)
const dragging = ref(false)
const dragValue = ref(0)
const now = ref(Date.now())
let ticker = null

const formatSpeed = (s) => `${toPersianDigits(String(s).replace('.', '٫'))}×`

const sleepLabel = computed(() => {
  if (!player.sleepEndsAt) return t('player.sleepTimer')
  const minutes = Math.max(1, Math.ceil((player.sleepEndsAt - now.value) / 60000))
  return t('player.sleepLeft', { n: formatNumber(minutes) })
})

const message = computed(() => {
  switch (player.status) {
    case 'loading':
      return t('common.loading')
    case 'preparing':
      return t('player.preparingLong')
    case 'error':
      return t(`player.errors.${player.error || 'network'}`)
    default:
      return ''
  }
})

function onDrag(value) {
  dragging.value = true
  dragValue.value = value
}

function onSeek(value) {
  player.seek(value)
  dragging.value = false
}

function pickSpeed(s) {
  player.setSpeed(s)
  speedOpen.value = false
}

function pickSleep(minutes) {
  player.setSleep(minutes)
  sleepOpen.value = false
}

function retry() {
  const track = player.current
  player.stop()
  if (track) player.play({ ...track, audio_duration: player.duration })
}

function close() {
  if (window.history.state?.back) router.back()
  else router.replace({ name: 'home' })
}

onMounted(() => (ticker = setInterval(() => (now.value = Date.now()), 15000)))
onBeforeUnmount(() => clearInterval(ticker))
</script>

<style scoped lang="scss">
.player {
  min-height: 100vh;
  max-width: 480px;
  margin: 0 auto;
  padding: 8px 20px calc(24px + env(safe-area-inset-bottom));
  background: linear-gradient(180deg, #2a1f6e 0%, var(--page) 70%);
  display: flex;
  flex-direction: column;
}

.top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.art {
  width: min(300px, 72vw);
  aspect-ratio: 1;
  margin: 18px auto 24px;
}

.title {
  font-size: 20px;
  line-height: 1.8;
  margin: 0;
}

.show {
  margin: 4px 0 12px;
}

.notice {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 8px 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--surface-2);
  font-size: 13px;

  &.error {
    background: rgba($negative, 0.2);
  }
}

.seek {
  margin-top: 14px;
}

.times {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  margin-top: 2px;
}

.controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 40px;
  margin: 28px 0 24px;
  // the seek bar and times run left-to-right: previous = left, next = right
  direction: ltr;
}

.skip {
  background: none;
  border: 0;
  color: #fff;
  font-family: inherit;
  display: flex;
  flex-direction: column;
  align-items: center;
  font-size: 12px;
  cursor: pointer;
}

.main {
  width: 76px;
  height: 76px;
  font-size: 22px;
}

.chips {
  display: flex;
  justify-content: center;
  gap: 12px;
}

.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 16px;
  border: 0;
  border-radius: 999px;
  background: var(--surface-2);
  color: #fff;
  font-family: inherit;
  font-size: 14px;
  cursor: pointer;
}

.empty {
  text-align: center;
  padding: 80px 0;
}

.sheet {
  width: 100%;
  max-width: 480px;
  background: var(--surface);
  border-radius: 20px 20px 0 0;
  padding: 8px 0 calc(12px + env(safe-area-inset-bottom));
}

.sheet-title {
  padding: 12px 20px;
  font-weight: 700;
}
</style>
