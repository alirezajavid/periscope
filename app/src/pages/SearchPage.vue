<template>
  <q-page class="app-page has-miniplayer">
    <h1 class="page-title">{{ $t('nav.search') }}</h1>

    <q-input
      v-model="query"
      outlined
      :placeholder="$t('search.placeholder')"
      :aria-label="$t('nav.search')"
      clearable
      hide-bottom-space
      @update:model-value="onType"
    >
      <template #prepend><q-icon name="search" class="muted" /></template>
    </q-input>

    <section v-if="tags.length" class="tags">
      <div class="muted label">{{ $t('search.popularTags') }}</div>
      <div class="tag-list">
        <button
          v-for="tag in tags"
          :key="tag.name"
          class="chip tag"
          :class="{ on: activeTag === tag.name }"
          type="button"
          @click="toggleTag(tag.name)"
        >
          #{{ tag.name }}
        </button>
      </div>
    </section>

    <div v-if="searched && !posts.loading" class="muted count">
      {{ $t('search.results', { count: formatNumber(posts.total) }) }}
    </div>

    <StatusPanel
      :loading="posts.loading && !posts.items.length"
      :error="!posts.items.length ? posts.error : ''"
      :empty="searched && !posts.loading && !posts.items.length && !posts.error"
      :empty-text="$t('search.none')"
      icon="search_off"
    >
      <EpisodeRow v-for="post in posts.items" :key="post.id" :post="post" />
      <div v-if="posts.hasMore()" class="more">
        <q-btn
          flat
          no-caps
          color="primary"
          :loading="posts.loading"
          :label="$t('search.more')"
          @click="posts.loadMore()"
        />
      </div>
    </StatusPanel>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import EpisodeRow from '@/components/EpisodeRow.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { usePostsStore } from '@/stores/usePostsStore'
import { formatNumber } from '@shared/utils/number.js'

const route = useRoute()
const router = useRouter()
const posts = usePostsStore()
const query = ref(route.query.q || '')
const activeTag = ref(route.query.tag || '')
const tags = ref([])
let timer = null

const searched = computed(() => Boolean(query.value || activeTag.value))

function run() {
  if (!searched.value) {
    posts.load()
    return
  }
  posts.load({ q: query.value || '', tag: activeTag.value })
}

function onType() {
  clearTimeout(timer)
  timer = setTimeout(() => {
    router.replace({ query: { q: query.value || undefined, tag: activeTag.value || undefined } })
    run()
  }, 350)
}

function toggleTag(name) {
  activeTag.value = activeTag.value === name ? '' : name
  router.replace({ query: { q: query.value || undefined, tag: activeTag.value || undefined } })
  run()
}

onMounted(async () => {
  run()
  try {
    tags.value = (await posts.fetchTags()).slice(0, 12)
  } catch {
    tags.value = []
  }
})
</script>

<style scoped lang="scss">
.tags {
  margin-top: 22px;
}

.label {
  margin-bottom: 10px;
  font-size: 14px;
}

.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag {
  border: 0;
  color: var(--primary);
  font-family: inherit;
  font-size: 14px;
  padding: 4px 14px;
  cursor: pointer;

  &.on {
    background: var(--primary);
    color: $dark-page;
    font-weight: 700;
  }
}

.count {
  margin: 20px 0 4px;
}

.more {
  display: flex;
  justify-content: center;
}
</style>
