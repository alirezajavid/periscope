<template>
  <nav class="tabbar" :aria-label="$t('nav.label')">
    <router-link
      v-for="tab in tabs"
      :key="tab.name"
      :to="{ name: tab.name }"
      class="tab"
      :class="{ active: isActive(tab) }"
    >
      <q-icon :name="tab.icon" size="26px" />
      <span>{{ $t(`nav.${tab.name}`) }}</span>
    </router-link>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()

const tabs = [
  { name: 'home', icon: 'o_home' },
  { name: 'search', icon: 'o_search' },
  { name: 'ask', icon: 'o_contact_support' },
  { name: 'account', icon: 'o_person' },
]

// Episode pages belong to the "home" tab
const isActive = (tab) => route.name === tab.name || (tab.name === 'home' && route.name === 'post')
</script>

<style scoped lang="scss">
.tabbar {
  position: fixed;
  inset: auto 0 0 0;
  z-index: 100;
  display: flex;
  justify-content: space-around;
  height: calc(var(--tabbar-height) + env(safe-area-inset-bottom));
  padding-bottom: env(safe-area-inset-bottom);
  background: var(--surface);
  border-top: 1px solid var(--line);
}

.tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  font-size: 12px;
  color: var(--muted);
  text-decoration: none;

  &.active {
    color: var(--primary);
    font-weight: 700;
  }
}
</style>
