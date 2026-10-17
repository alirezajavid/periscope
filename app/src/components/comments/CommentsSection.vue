<template>
  <section class="comments">
    <h2 class="heading">{{ $t('comments.title') }}</h2>

    <form class="composer" @submit.prevent="submit">
      <div v-if="replyTo" class="replying muted">
        <span>{{ $t('comments.replyingTo', { name: replyTo.author?.name || '' }) }}</span>
        <q-btn flat dense round size="sm" icon="close" :aria-label="$t('common.cancel')" @click="replyTo = null" />
      </div>
      <q-input
        ref="input"
        v-model="text"
        outlined
        dark
        autogrow
        type="textarea"
        maxlength="1000"
        :placeholder="replyTo ? $t('comments.replyPlaceholder') : $t('comments.placeholder')"
        class="field"
      />
      <p v-if="sendError" class="error">{{ sendError }}</p>
      <q-btn
        type="submit"
        class="send"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :loading="sending"
        :disable="text.trim().length < 2"
        :label="replyTo ? $t('comments.sendReply') : $t('comments.send')"
      />
    </form>

    <StatusPanel
      :loading="comments.loading && !comments.items.length"
      :error="comments.items.length ? '' : comments.error"
      :retry="() => comments.load(postId)"
      :empty="!comments.loading && !comments.error && !comments.items.length"
      :empty-text="$t('comments.none')"
      icon="chat_bubble_outline"
    >
      <div class="list">
        <div v-for="thread in comments.items" :key="thread.id" class="thread">
          <CommentItem :comment="thread" @reply="startReply" @delete="confirmDelete" />
          <div v-if="thread.replies?.length" class="replies">
            <CommentItem
              v-for="reply in thread.replies"
              :key="reply.id"
              :comment="reply"
              reply
              @reply="startReply"
              @delete="confirmDelete"
            />
          </div>
        </div>
        <q-btn
          v-if="comments.hasMore()"
          flat
          no-caps
          color="primary"
          class="more"
          :loading="comments.loading"
          :label="$t('comments.more')"
          @click="comments.loadMore()"
        />
      </div>
    </StatusPanel>
  </section>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import { Dialog } from 'quasar'
import { useI18n } from 'vue-i18n'
import CommentItem from '@/components/comments/CommentItem.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { useCommentsStore } from '@/stores/useCommentsStore'

const props = defineProps({ postId: { type: [Number, String], required: true } })

const { t } = useI18n()
const comments = useCommentsStore()
const text = ref('')
const replyTo = ref(null)
const sending = ref(false)
const sendError = ref('')
const input = ref(null)

async function startReply(comment) {
  replyTo.value = comment
  await nextTick()
  input.value?.focus()
}

async function submit() {
  const body = text.value.trim()
  if (body.length < 2 || sending.value) return
  sending.value = true
  sendError.value = ''
  try {
    await comments.add(body, replyTo.value?.id ?? null)
    text.value = ''
    replyTo.value = null
  } catch (e) {
    sendError.value = e.response?.data?.message || t('common.genericError')
  } finally {
    sending.value = false
  }
}

function confirmDelete(comment) {
  Dialog.create({
    message: t('comments.deleteConfirm'),
    cancel: t('common.cancel'),
    ok: { label: t('comments.delete'), color: 'negative' },
    dark: true,
  }).onOk(() => comments.remove(comment).catch(() => {}))
}

watch(
  () => props.postId,
  (id) => {
    replyTo.value = null
    text.value = ''
    if (id) comments.load(id)
  },
  { immediate: true },
)
</script>

<style scoped lang="scss">
.comments {
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid var(--line);
}

.heading {
  font-size: 18px;
  margin: 0 0 12px;
}

.composer {
  margin-bottom: 20px;
}

.replying {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  margin-bottom: 6px;
}

// The shared input style has a fixed 52px height; a growing textarea needs auto.
.field :deep(.q-field__control) {
  height: auto;
  min-height: 52px;
}

.error {
  color: var(--red);
  font-size: 13px;
  margin: 6px 0 0;
}

.send {
  margin-top: 10px;
  border-radius: 12px;
}

.list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.replies {
  margin-inline-start: 20px;
  padding-inline-start: 12px;
  border-inline-start: 1px solid var(--line);
}

.more {
  align-self: center;
}
</style>
