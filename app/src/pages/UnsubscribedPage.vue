<template>
  <div class="center-page">
    <div class="icon-circle"><q-icon name="notifications_off" size="40px" color="primary" /></div>
    <h1 class="title">{{ $t('unsubscribed.title') }}</h1>
    <p class="muted">{{ $t('unsubscribed.text') }}</p>
    <q-btn
      class="btn"
      color="primary"
      text-color="dark"
      unelevated
      no-caps
      :label="$t('common.continue')"
      :to="{ name: 'home' }"
    />
  </div>
</template>

<style scoped lang="scss">
.center-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding-top: 96px;
}

.icon-circle {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: rgba($primary, 0.18);
  display: grid;
  place-items: center;
  margin-bottom: 24px;
}

.title {
  font-size: 20px;
  font-weight: 700;
  margin: 0 0 8px;
}
</style>
