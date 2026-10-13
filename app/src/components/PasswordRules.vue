<template>
  <ul class="rules">
    <li v-for="rule in rules" :key="rule.key" :class="{ ok: rule.ok }">
      <q-icon :name="rule.ok ? 'check_circle' : 'cancel'" size="22px" />
      <span>{{ rule.label }}</span>
    </li>
  </ul>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  password: { type: String, default: '' },
  // Pass the confirmation to also show the "both match" rule
  confirmation: { type: String, default: null },
})

const { t } = useI18n()

// Same rules the API enforces (Password::min(8)->numbers()->mixedCase())
const rules = computed(() => {
  const list = [
    { key: 'length', ok: props.password.length >= 8, label: t('password.length') },
    { key: 'digit', ok: /\d/.test(props.password), label: t('password.digit') },
    {
      key: 'case',
      ok: /[A-Z]/.test(props.password) && /[a-z]/.test(props.password),
      label: t('password.case'),
    },
  ]
  if (props.confirmation !== null) {
    list.push({
      key: 'match',
      ok: props.password !== '' && props.password === props.confirmation,
      label: t('password.match'),
    })
  }
  return list
})
</script>

<style scoped lang="scss">
.rules {
  list-style: none;
  margin: 10px 0 0;
  padding: 0;
}

li {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 28px;
  color: var(--muted);
  font-size: 14px;
}

li.ok {
  color: $accent-light;

  .q-icon {
    color: $accent;
  }
}
</style>
