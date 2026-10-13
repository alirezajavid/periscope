<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <q-page class="flex flex-center q-pa-md">
        <div class="auth-card column q-gutter-y-md">
          <div class="text-h5 text-center">{{ $t('app.name') }}</div>
          <router-view />
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<style scoped>
.auth-card {
  width: 100%;
  max-width: 400px;
}
</style>
