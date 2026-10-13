<template>
  <div
    v-if="player.hasTrack"
    class="mini"
    role="region"
    :aria-label="$t('player.mini')"
    @click="router.push({ name: 'player' })"
  >
    <div class="cover"><CoverImage :url="player.current.cover_url" /></div>
    <div class="text">
      <div class="title">{{ toPersianDigits(player.current.title) }}</div>
      <div class="time muted">
        <template v-if="player.status === 'preparing'">{{ $t('player.preparing') }}</template>
        <template v-else-if="player.status === 'loading'">{{ $t('common.loading') }}</template>
        <template v-else>{{
          $t('player.timeOf', {
            position: formatDuration(player.position),
            duration: formatDuration(player.duration),
          })
        }}</template>
      </div>
    </div>
    <q-btn
      flat
      round
      color="white"
      :icon="player.isPlaying ? 'pause' : 'play_arrow'"
      :aria-label="player.isPlaying ? $t('player.pause') : $t('player.play')"
      @click.stop="player.toggle()"
    />
    <div class="bar" :style="{ width: progress + '%' }" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import CoverImage from '@/components/CoverImage.vue'
import { usePlayerStore } from '@/stores/usePlayerStore'
import { formatDuration, toPersianDigits } from '@shared/utils/number.js'

const router = useRouter()
const player = usePlayerStore()
const progress = computed(() =>
  player.duration ? Math.min(100, (player.position / player.duration) * 100) : 0,
)
</script>

<style scoped lang="scss">
.mini {
  position: fixed;
  inset-inline: 12px;
  bottom: calc(var(--tabbar-height) + 10px + env(safe-area-inset-bottom));
  z-index: 99;
  max-width: 456px;
  margin-inline: auto;
  height: var(--miniplayer-height);
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 8px 6px 12px;
  border-radius: 18px;
  background: var(--surface-2);
  overflow: hidden;
  cursor: pointer;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
}

.cover {
  flex: none;
  width: 48px;
  height: 48px;
}

.text {
  flex: 1;
  min-width: 0;
}

.title {
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.time {
  font-size: 12px;
}

.bar {
  position: absolute;
  inset-block-end: 0;
  inset-inline-start: 0;
  height: 3px;
  background: var(--primary);
}
</style>
