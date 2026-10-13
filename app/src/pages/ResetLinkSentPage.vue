<template>
  <div class="center-page">
    <div class="icon-circle"><q-icon name="mail_outline" size="40px" color="positive" /></div>
    <h1 class="title">{{ $t('forgot.sentTitle') }}</h1>
    <p class="muted text">{{ $t('forgot.sentText', { email: route.query.email }) }}</p>
    <q-btn
      class="btn"
      outline
      no-caps
      color="primary"
      :label="$t('forgot.backToLogin')"
      :to="{ name: 'login' }"
    />
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

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
  background: rgba($accent, 0.18);
  display: grid;
  place-items: center;
  margin-bottom: 24px;
}

.title {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 12px;
}

.text {
  line-height: 2;
  margin-bottom: 24px;
}

.btn {
  border-radius: 16px;
}
</style>
