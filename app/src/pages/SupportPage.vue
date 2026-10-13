<template>
  <q-page class="app-page">
    <PageHeader :title="$t('account.support')" fallback="/account" />
    <StatusPanel
      :loading="loading"
      :error="error"
      :empty="!loading && !items.length && !links.length"
      :retry="load"
    >
      <section v-if="items.length">
        <article v-for="item in items" :key="item.title" class="card item">
          <h2>{{ item.title }}</h2>
          <a
            v-if="item.kind === 'link'"
            class="btn-link"
            :href="item.value"
            target="_blank"
            rel="noopener noreferrer"
            dir="ltr"
            >{{ item.value }}</a
          >
          <template v-else>
            <div class="copy" dir="ltr">
              <span class="code">{{ item.value }}</span>
              <q-btn
                flat
                round
                dense
                icon="content_copy"
                color="primary"
                :aria-label="$t('payment.copy')"
                @click="copy(item.value)"
              />
            </div>
            <div v-if="item.qr_svg" class="qr" v-html="item.qr_svg" />
            <p v-if="item.network" class="warn">
              {{ $t('support.network', { network: item.network }) }}
            </p>
            <p v-if="item.note" class="muted small">{{ item.note }}</p>
          </template>
        </article>
      </section>

      <section v-if="links.length">
        <h2 class="head">{{ $t('support.links') }}</h2>
        <a
          v-for="link in links"
          :key="link.url"
          class="card link"
          :href="link.url"
          target="_blank"
          rel="noopener noreferrer"
        >
          <q-icon name="open_in_new" />
          <span>{{ link.title }}</span>
        </a>
      </section>
    </StatusPanel>
  </q-page>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import PageHeader from '@/components/PageHeader.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { api } from '@/boot/axios'
import { useAsync } from '@/composables/useAsync'

const { t } = useI18n()
const items = ref([])
const links = ref([])
const { loading, error, run } = useAsync(
  async () => {
    const [support, settings] = await Promise.all([
      api.get('/api/support'),
      api.get('/api/settings'),
    ])
    items.value = support.data.data
    links.value = settings.data.data.social_links ?? []
  },
  { fallbackMessage: t('common.genericError') },
)
const load = run

async function copy(value) {
  await navigator.clipboard?.writeText(value)
  Notify.create({ message: t('common.copied'), type: 'positive', timeout: 1500 })
}

onMounted(load)
</script>

<style scoped lang="scss">
.item {
  margin-bottom: 14px;
  text-align: center;

  h2 {
    font-size: 17px;
    margin: 0 0 8px;
  }
}

.copy {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  word-break: break-all;
}

.code {
  font-size: 14px;
}

.qr {
  width: 180px;
  margin: 12px auto 0;
  background: #fff;
  border-radius: 12px;
  padding: 8px;
}

.warn {
  color: var(--amber);
  font-size: 13px;
  margin: 12px 0 0;
}

.small {
  font-size: 12px;
}

.head {
  font-size: 17px;
  margin: 24px 0 10px;
}

.link {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
  color: #fff;
  text-decoration: none;
}
</style>
