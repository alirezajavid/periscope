<template>
  <div class="not-found">
    <div class="code">۴۰۴</div>
    <h1>{{ $t('notFound.title') }}</h1>
    <q-btn
      color="primary"
      text-color="dark"
      unelevated
      no-caps
      :label="$t('notFound.home')"
      :to="{ name: 'home' }"
    />
  </div>
</template>

<style scoped lang="scss">
.not-found {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  text-align: center;
}

.code {
  font-size: 72px;
  font-weight: 700;
  color: var(--primary);
}

h1 {
  font-size: 20px;
  margin: 0 0 8px;
}
</style>
