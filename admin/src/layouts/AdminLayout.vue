<template>
  <q-layout view="hHh LpR lFf">
    <q-header elevated>
      <q-toolbar>
        <q-btn
          flat
          dense
          round
          icon="menu"
          :aria-label="$t('nav.menu')"
          @click="drawer = !drawer"
        />
        <q-toolbar-title>{{ $t('app.name') }}</q-toolbar-title>
        <span class="user">{{ auth.user?.name }}</span>
        <q-btn flat dense no-caps icon="logout" :label="$t('nav.logout')" @click="signOut" />
      </q-toolbar>
    </q-header>

    <!-- Desktop-first: a permanent sidebar; on phones it becomes an overlay (breakpoint) -->
    <q-drawer v-model="drawer" side="left" show-if-above bordered :breakpoint="800">
      <q-list padding>
        <q-item
          v-for="item in items"
          :key="item.name"
          :to="{ name: item.name }"
          clickable
          v-ripple
          active-class="text-primary bg-blue-1"
        >
          <q-item-section avatar><q-icon :name="item.icon" /></q-item-section>
          <q-item-section>{{ $t(`nav.${item.name}`) }}</q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminAuthStore } from '@/stores/useAdminAuthStore'

const router = useRouter()
const auth = useAdminAuthStore()
const drawer = ref(false)

const items = [
  { name: 'posts', icon: 'article' },
  { name: 'users', icon: 'group' },
  { name: 'payments', icon: 'receipt_long' },
  { name: 'questions', icon: 'contact_support' },
  { name: 'stats', icon: 'insights' },
  { name: 'leaks', icon: 'travel_explore' },
  { name: 'settings', icon: 'settings' },
].filter((item) => router.hasRoute(item.name))

async function signOut() {
  await auth.logout()
  router.replace({ name: 'login' })
}
</script>

<style scoped>
.user {
  margin-inline: 12px;
  font-size: 14px;
}
</style>
