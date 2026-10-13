<template>
  <q-page class="app-page">
    <PageHeader :title="$t('payments.title')" fallback="/account" />
    <q-banner v-if="route.query.submitted" class="bg-positive text-white q-mb-md" rounded dense>{{
      $t('payments.submitted')
    }}</q-banner>

    <StatusPanel
      :loading="loading"
      :error="error"
      :empty="!loading && !subscription.payments.length"
      :empty-text="$t('payments.none')"
      icon="receipt_long"
      :retry="run"
    >
      <article v-for="p in subscription.payments" :key="p.id" class="card pay">
        <div class="row">
          <strong>{{ $t('plans.subscription', { n: formatNumber(p.plan.months) }) }}</strong>
          <span class="chip" :class="chipClass(p.status)">{{
            $t(`payments.status.${p.status}`)
          }}</span>
        </div>
        <div class="muted small">
          {{ formatNumber(Math.round(p.amount)) }} {{ $t(`currency.${p.currency}`) }} ·
          {{ formatDate(p.created_at) }} · {{ $t('payments.tracking') }}: {{ p.tracking_code }}
        </div>
        <div v-if="p.status === 'rejected' && p.reject_reason" class="reason">
          {{ $t('payments.reason', { reason: p.reject_reason }) }}
        </div>
      </article>
    </StatusPanel>
  </q-page>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '@/components/PageHeader.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { useAsync } from '@/composables/useAsync'
import { useSubscriptionStore } from '@/stores/useSubscriptionStore'
import { formatDate } from '@shared/utils/date.js'
import { formatNumber } from '@shared/utils/number.js'

const route = useRoute()
const { t } = useI18n()
const subscription = useSubscriptionStore()
const { loading, error, run } = useAsync(() => subscription.fetchPayments(), {
  fallbackMessage: t('common.genericError'),
})

const chipClass = (status) => ({ approved: 'chip--ok', rejected: 'chip--bad', pending: '' })[status]

onMounted(run)
</script>

<style scoped lang="scss">
.pay {
  margin-bottom: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.small {
  font-size: 12px;
  line-height: 1.9;
}

.reason {
  color: var(--amber);
  font-size: 13px;
  line-height: 1.9;
}

.chip--bad {
  background: rgba($negative, 0.2);
  color: $negative;
}
</style>
