<template>
  <div class="not-found">
    <div class="text-h2 text-primary">۴۰۴</div>
    <h1 class="text-h5">{{ $t('notFound.title') }}</h1>
    <q-btn color="primary" unelevated no-caps :label="$t('common.back')" to="/" />
  </div>
</template>

<style scoped>
.not-found {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
}
</style>
