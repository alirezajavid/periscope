<template>
  <article class="comment" :class="{ 'comment--admin': isAdmin, 'comment--reply': reply }">
    <p v-if="comment.deleted" class="muted deleted">{{ $t('comments.deleted') }}</p>

    <template v-else>
      <header class="head">
        <span class="name">{{ comment.author?.name }}</span>
        <span v-if="isAdmin" class="chip chip--admin">
          <q-icon name="verified" size="14px" /> {{ $t('comments.adminBadge') }}
        </span>
        <span class="muted date">{{ formatDate(comment.created_at) }}</span>
      </header>

      <!-- Plain text, never v-html -->
      <p class="body" dir="auto">{{ comment.body }}</p>

      <footer class="actions">
        <ReactionBar
          small
          :value="comment.reactions"
          :react="(type) => comments.reactToComment(comment, type)"
        />
        <q-btn flat dense no-caps size="sm" class="action" :label="$t('comments.reply')" @click="$emit('reply', comment)" />
        <q-btn
          v-if="comment.mine"
          flat
          dense
          no-caps
          size="sm"
          class="action"
          :label="$t('comments.delete')"
          @click="$emit('delete', comment)"
        />
      </footer>
    </template>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import ReactionBar from '@/components/comments/ReactionBar.vue'
import { useCommentsStore } from '@/stores/useCommentsStore'
import { formatDate } from '@shared/utils/date.js'

const props = defineProps({
  comment: { type: Object, required: true },
  reply: { type: Boolean, default: false },
})
defineEmits(['reply', 'delete'])

const comments = useCommentsStore()
const isAdmin = computed(() => props.comment.author?.is_admin === true)
</script>

<style scoped lang="scss">
.comment {
  padding: 12px 14px;
  border-radius: 16px;
  background: var(--surface);
}

.comment--reply {
  background: transparent;
  padding: 10px 0 0;
}

// Admin replies stand out a little: tinted background and an accent edge.
.comment--admin {
  background: rgba($primary, 0.1);
  border-inline-start: 3px solid var(--primary);
  padding: 10px 12px;
}

.comment--reply.comment--admin {
  margin-top: 10px;
  border-radius: 12px;
}

.head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 13px;
}

.name {
  font-weight: 700;
}

.chip--admin {
  gap: 4px;
  background: rgba($primary, 0.18);
  color: var(--primary);
  line-height: 20px;
}

.date {
  font-size: 12px;
  margin-inline-start: auto;
}

.body {
  margin: 6px 0 4px;
  line-height: 1.9;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.deleted {
  margin: 0;
  font-size: 13px;
}

.actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

.action {
  color: var(--muted);
}
</style>
