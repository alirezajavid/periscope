<template>
  <div class="install-page">
    <img class="app-icon" src="/icons/icon-192x192.png" alt="" />
    <h1 class="title">{{ $t('install.title') }}</h1>

    <!-- Already running from the home screen -->
    <template v-if="standalone || installed">
      <p class="muted">{{ $t('install.alreadyInstalled') }}</p>
    </template>

    <template v-else>
      <p class="muted intro">{{ $t('install.intro') }}</p>

      <!-- Android / desktop Chromium: the browser's own install dialog -->
      <q-btn
        v-if="deferredPrompt"
        class="btn install-btn"
        color="primary"
        text-color="dark"
        unelevated
        no-caps
        icon="install_mobile"
        :label="$t('install.installNow')"
        @click="promptInstall"
      />

      <template v-else>
        <ol class="steps card">
          <li v-for="(step, i) in steps" :key="i" class="step">
            <span class="step-num">{{ i + 1 }}</span>
            <span class="step-text">{{ step.text }}</span>
            <q-icon v-if="step.icon" :name="step.icon" size="24px" class="step-icon" />
          </li>
        </ol>
        <p v-if="os === 'ios' && !iosSafari" class="note">{{ $t('install.ios.useSafari') }}</p>
      </template>

      <!-- Extra notes the admin keeps in settings, if any (sanitised by the API) -->
      <div v-if="extra" class="prose extra" dir="rtl" v-html="extra" />
    </template>

    <q-btn
      class="btn"
      :class="standalone || installed ? '' : 'continue-btn'"
      :color="standalone || installed ? 'primary' : undefined"
      :text-color="standalone || installed ? 'dark' : undefined"
      :flat="!(standalone || installed)"
      unelevated
      no-caps
      :label="standalone || installed ? $t('common.continue') : $t('install.continueInBrowser')"
      @click="continueOn"
    />
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { isIosSafari, isStandalone, platform, useInstall } from '@/composables/useInstall'
import { useSettingsStore } from '@/stores/useSettingsStore'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const settings = useSettingsStore()
const { deferredPrompt, installed, promptInstall } = useInstall()

const standalone = isStandalone()
const os = platform()
const iosSafari = isIosSafari()

const steps = computed(() => {
  if (os === 'ios')
    return [
      { text: t('install.ios.step1'), icon: 'ios_share' },
      { text: t('install.ios.step2'), icon: 'add_box' },
      { text: t('install.ios.step3') },
    ]
  if (os === 'android')
    return [
      { text: t('install.android.step1'), icon: 'more_vert' },
      { text: t('install.android.step2'), icon: 'add_to_home_screen' },
      { text: t('install.android.step3') },
    ]
  return [
    { text: t('install.desktop.step1'), icon: 'install_desktop' },
    { text: t('install.desktop.step2') },
  ]
})

const extra = computed(() => settings.data.page_install_guide || '')

/** Back to where the user was going; only same-app paths are accepted. */
function continueOn() {
  const target = route.query.redirect
  const safe = typeof target === 'string' && target.startsWith('/') && !target.startsWith('//')
  router.replace(safe ? target : '/')
}

onMounted(() => {
  if (!settings.loaded) settings.fetch()
})
</script>

<style scoped lang="scss">
.install-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding-top: 48px;
}

.app-icon {
  width: 96px;
  height: 96px;
  border-radius: 22px;
  margin-bottom: 20px;
}

.title {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 8px;
}

.intro {
  margin: 0 0 24px;
  line-height: 1.9;
}

.install-btn {
  margin-bottom: 12px;
}

.steps {
  list-style: none;
  width: 100%;
  margin: 0 0 12px;
  padding: 8px 16px;
  text-align: start;
}

.step {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;

  & + & {
    border-top: 1px solid var(--line);
  }
}

.step-num {
  flex: none;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--surface-2);
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 14px;
}

.step-text {
  flex: 1;
  line-height: 1.8;
}

.step-icon {
  color: var(--primary);
}

.note {
  color: var(--amber);
  font-size: 13px;
  margin: 0 0 12px;
}

.extra {
  width: 100%;
  text-align: start;
  font-size: 14px;
  color: var(--muted);
}

.continue-btn {
  color: var(--muted);
  margin-top: 8px;
}
</style>
