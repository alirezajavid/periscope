<template>
  <q-page class="app-page has-miniplayer">
    <h1 class="page-title">{{ $t('ask.title') }}</h1>
    <p class="muted intro">{{ $t('ask.intro') }}</p>

    <template v-if="subscription.active">
      <q-banner v-if="message" class="bg-negative text-white q-mb-md" rounded dense>{{
        message
      }}</q-banner>
      <q-input
        v-model="body"
        type="textarea"
        outlined
        autogrow
        :maxlength="500"
        rows="5"
        :placeholder="$t('ask.placeholder')"
        :error="!!errors.body"
        :error-message="errors.body"
        hide-bottom-space
      />
      <div class="counter muted">{{ formatNumber(body.length) }} / {{ formatNumber(500) }}</div>
      <q-btn
        class="btn"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        :label="$t('ask.send')"
        :loading="loading"
        :disable="body.trim().length < 5"
        @click="send"
      />
    </template>
    <div v-else-if="subscription.status" class="card lock">
      <q-icon name="lock_outline" size="28px" />
      <p>{{ $t('ask.subscribersOnly') }}</p>
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

    <h2>{{ $t('ask.previous') }}</h2>
    <StatusPanel
      :loading="listLoading"
      :error="listError"
      :empty="!listLoading && !questions.items.length"
      :empty-text="$t('ask.none')"
      :retry="load"
    >
      <article v-for="q in questions.items" :key="q.id" class="card q">
        <p class="q-body">{{ q.body }}</p>
        <div v-if="q.answer" class="answer">{{ q.answer }}</div>
        <div class="q-foot">
          <span class="muted small">{{ formatDate(q.created_at) }}</span>
          <router-link
            v-if="q.answered_in_post"
            class="chip chip--ok"
            :to="{ name: 'post', params: { id: q.answered_in_post.id } }"
          >
            {{
              $t('ask.answeredIn', {
                episode: formatNumber(q.answered_in_post.episode_number ?? q.answered_in_post.id),
              })
            }}
          </router-link>
          <span v-else-if="q.status === 'answered'" class="chip chip--ok">{{
            $t('ask.answered')
          }}</span>
          <span v-else class="chip">{{ $t('ask.received') }}</span>
        </div>
      </article>
    </StatusPanel>
  </q-page>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { useApiForm } from '@/composables/useApiForm'
import { useAsync } from '@/composables/useAsync'
import { useQuestionsStore } from '@/stores/useQuestionsStore'
import { useSubscriptionStore } from '@/stores/useSubscriptionStore'
import { formatDate } from '@shared/utils/date.js'
import { formatNumber } from '@shared/utils/number.js'

const questions = useQuestionsStore()
const subscription = useSubscriptionStore()
const body = ref('')
const { loading, errors, message, submit } = useApiForm()
const { loading: listLoading, error: listError, run: load } = useAsync(() => questions.fetch())

async function send() {
  if (await submit(() => questions.ask(body.value.trim()))) body.value = ''
}

onMounted(async () => {
  if (!subscription.status) subscription.fetchStatus().catch(() => {})
  load()
})
</script>

<style scoped lang="scss">
.intro {
  line-height: 2;
  margin: 0 0 16px;
}

.counter {
  text-align: end;
  font-size: 12px;
  margin: 8px 0 16px;
}

h2 {
  font-size: 18px;
  margin: 28px 0 12px;
}

.lock {
  text-align: center;

  p {
    margin: 8px 0 16px;
  }
}

.q {
  margin-bottom: 12px;
}

.q-body {
  margin: 0 0 12px;
  line-height: 2;
}

.answer {
  background: var(--surface-2);
  border-radius: 12px;
  padding: 10px 12px;
  margin-bottom: 12px;
  line-height: 2;
}

.q-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.small {
  font-size: 12px;
}

a.chip {
  text-decoration: none;
  color: inherit;
}
</style>
