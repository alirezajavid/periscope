<template>
  <q-banner
    :class="`bg-${color[result.result]} text-${result.result === 'ambiguous' ? 'dark' : 'white'}`"
    dense
  >
    <div class="text-subtitle2">{{ $t(`leaks.result.${result.result}`) }}</div>
    <div v-for="user in result.users" :key="user.id">
      <router-link class="text-inherit" :to="{ name: 'user', params: { id: user.id } }">{{
        user.name
      }}</router-link>
      <span class="ltr q-mr-sm">{{ user.email || user.phone }}</span>
    </div>
  </q-banner>
</template>

<script setup>
// Outcome of one leak check: the verdict and the candidate users.
defineProps({ result: { type: Object, required: true } })

const color = { found: 'positive', ambiguous: 'warning', not_found: 'grey-7' }
</script>

<style scoped>
.text-inherit {
  color: inherit;
  text-decoration: underline;
}
</style>
