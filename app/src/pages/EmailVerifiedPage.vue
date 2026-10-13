<template>
  <div class="column q-gutter-y-md items-center">
    <q-icon name="check_circle" color="positive" size="64px" />
    <div class="text-subtitle1">{{ $t('auth.emailVerified') }}</div>
    <q-btn color="primary" no-caps :to="{ name: 'home' }" :label="$t('auth.continue')" />
  </div>
</template>
