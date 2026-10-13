<template>
  <article class="row" @click="open">
    <div class="thumb"><CoverImage :url="post.cover_url" /></div>
    <div class="info">
      <h3 class="title">{{ toPersianDigits(post.title) }}</h3>
      <div class="meta muted">
        <span>{{ meta }}</span>
        <span class="chip" :class="post.access === 'free' ? 'chip--free' : 'chip--premium'">
          {{ post.access === 'free' ? $t('post.free') : $t('post.premium') }}
        </span>
      </div>
    </div>
    <q-btn
      v-if="post.has_audio"
      class="play"
      round
      unelevated
      color="primary"
      text-color="dark"
      :icon="post.locked ? 'lock' : isCurrentPlaying ? 'pause' : 'play_arrow'"
      :aria-label="post.locked ? $t('post.locked') : $t('post.play')"
      @click.stop="onPlay"
    />
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import CoverImage from '@/components/CoverImage.vue'
import { usePlayerStore } from '@/stores/usePlayerStore'
import { formatDate } from '@shared/utils/date.js'
import { formatDuration, toPersianDigits } from '@shared/utils/number.js'

const props = defineProps({ post: { type: Object, required: true } })

const router = useRouter()
const player = usePlayerStore()

const meta = computed(() =>
  [
    formatDate(props.post.published_at),
    props.post.audio_duration ? formatDuration(props.post.audio_duration) : null,
  ]
    .filter(Boolean)
    .join(' · '),
)
const isCurrentPlaying = computed(() => player.current?.id === props.post.id && player.isPlaying)

function open() {
  router.push({ name: 'post', params: { id: props.post.id } })
}

function onPlay() {
  if (props.post.locked) return open()
  if (player.current?.id === props.post.id) return player.toggle()
  player.play(props.post)
}
</script>

<style scoped lang="scss">
.row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  cursor: pointer;
}

.thumb {
  flex: none;
  width: 56px;
  height: 56px;
}

.info {
  flex: 1;
  min-width: 0;
}

.title {
  font-size: 15px;
  font-weight: 600;
  line-height: 1.8;
  margin: 0;
}

.meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.play {
  flex: none;
  width: 44px;
  height: 44px;
}
</style>
