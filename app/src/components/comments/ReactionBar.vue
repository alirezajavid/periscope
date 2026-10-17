<template>
  <div class="reactions" :class="{ 'reactions--small': small }">
    <button
      type="button"
      class="reaction"
      :class="{ 'reaction--on': value.mine === 'like' }"
      :aria-pressed="value.mine === 'like'"
      :aria-label="$t('comments.like')"
      :disabled="busy"
      @click="toggle('like')"
    >
      <q-icon :name="value.mine === 'like' ? 'thumb_up' : 'thumb_up_off_alt'" :size="iconSize" />
      <span>{{ toPersianDigits(value.likes) }}</span>
    </button>
    <button
      type="button"
      class="reaction"
      :class="{ 'reaction--on': value.mine === 'dislike' }"
      :aria-pressed="value.mine === 'dislike'"
      :aria-label="$t('comments.dislike')"
      :disabled="busy"
      @click="toggle('dislike')"
    >
      <q-icon
        :name="value.mine === 'dislike' ? 'thumb_down' : 'thumb_down_off_alt'"
        :size="iconSize"
      />
      <span>{{ toPersianDigits(value.dislikes) }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { toPersianDigits } from '@shared/utils/number.js'

// Tapping the active button again removes the reaction.
const props = defineProps({
  value: { type: Object, default: () => ({ likes: 0, dislikes: 0, mine: null }) },
  // async (type) => newSummary
  react: { type: Function, required: true },
  small: { type: Boolean, default: false },
})
const emit = defineEmits(['update'])

const busy = ref(false)
const iconSize = computed(() => (props.small ? '16px' : '20px'))

async function toggle(type) {
  if (busy.value) return
  busy.value = true
  try {
    const summary = await props.react(props.value.mine === type ? null : type)
    if (summary) emit('update', summary)
  } catch {
    // Leave the counts as they were; the next load shows the real state.
  } finally {
    busy.value = false
  }
}
</script>

<style scoped lang="scss">
.reactions {
  display: flex;
  gap: 8px;
}

.reaction {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: transparent;
  color: var(--muted);
  font: inherit;
  font-size: 14px;
  cursor: pointer;
  touch-action: manipulation;

  &:disabled {
    opacity: 0.6;
  }
}

.reaction--on {
  color: var(--primary);
  border-color: var(--primary);
}

.reactions--small .reaction {
  padding: 2px 10px;
  font-size: 12px;
  border-color: transparent;
}
</style>
