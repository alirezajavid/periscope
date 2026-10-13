<template>
  <q-page class="app-page">
    <PageHeader :title="$t('plans.title')" fallback="/account" />
    <p class="muted">{{ $t('plans.intro') }}</p>

    <StatusPanel
      :loading="loading"
      :error="error"
      :empty="!loading && !subscription.plans.length"
      :retry="load"
    >
      <label
        v-for="plan in subscription.plans"
        :key="plan.id"
        class="plan"
        :class="{ selected: selected === plan.id }"
      >
        <input v-model="selected" class="radio" type="radio" name="plan" :value="plan.id" />
        <span class="mark"><q-icon v-if="selected === plan.id" name="check" size="18px" /></span>
        <span class="info">
          <span class="name">
            {{ $t('plans.months', { n: formatNumber(plan.months) }) }}
            <span v-if="badge(plan)" class="chip chip--premium">{{
              $t(`plans.${badge(plan)}`)
            }}</span>
          </span>
          <span class="muted small">{{ $t('plans.perMonth', { price: perMonth(plan) }) }}</span>
        </span>
        <span class="price" :class="{ on: selected === plan.id }">
          <strong>{{ price(plan) }}</strong>
          <span class="muted small">{{ currencyLabel }}</span>
        </span>
      </label>
    </StatusPanel>

    <p v-if="isDev" class="warn">{{ $t('plans.placeholderNote') }}</p>

    <q-btn
      class="btn"
      color="primary"
      text-color="dark"
      unelevated
      no-caps
      :label="$t('common.continue')"
      :disable="!selected"
      :to="{ name: 'payment', query: { plan: selected } }"
    />
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { useAsync } from '@/composables/useAsync'
import { useAuthStore } from '@/stores/useAuthStore'
import { useSubscriptionStore } from '@/stores/useSubscriptionStore'
import { formatNumber } from '@shared/utils/number.js'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const auth = useAuthStore()
const subscription = useSubscriptionStore()
const selected = ref(null)
const isDev = import.meta.env.DEV
const { loading, error, run } = useAsync(() => subscription.fetchPlans(), {
  fallbackMessage: t('common.genericError'),
})

// Users who chose the foreign server pay in USD; everyone else in toman.
const currency = computed(() => (auth.user?.preferred_mirror === 'intl' ? 'USD' : 'IRT'))
const currencyLabel = computed(() => t(`currency.${currency.value}`))

const amountOf = (plan) =>
  plan.prices.find((p) => p.currency === currency.value)?.amount ?? plan.prices[0]?.amount ?? 0
const price = (plan) => formatNumber(Math.round(amountOf(plan)))
const perMonth = (plan) => formatNumber(Math.round(amountOf(plan) / plan.months))

// Recommended = 6 months, best value = the longest plan
function badge(plan) {
  const longest = Math.max(...subscription.plans.map((p) => p.months))
  if (plan.months === longest && subscription.plans.length > 1) return 'bestValue'
  if (plan.months === 6) return 'recommended'
  return null
}

async function load() {
  await run()
  selected.value =
    subscription.plans.find((p) => p.months === 6)?.id ?? subscription.plans[0]?.id ?? null
}

onMounted(load)
</script>

<style scoped lang="scss">
.plan {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  background: var(--surface);
  border: 2px solid var(--line);
  border-radius: 20px;
  padding: 18px 16px;
  margin-bottom: 16px;
  cursor: pointer;

  &.selected {
    border-color: var(--primary);
    background: var(--surface-2);
  }
}

.radio {
  position: absolute;
  opacity: 0;
}

.mark {
  flex: none;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid var(--muted);
  display: grid;
  place-items: center;

  .selected & {
    border-color: var(--primary);
    background: var(--primary);
    color: $dark-page;
  }
}

.info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.name {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 17px;
  font-weight: 700;
}

.price {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  font-size: 18px;

  &.on strong {
    color: var(--primary);
  }
}

.small {
  font-size: 12px;
}

.warn {
  color: var(--amber);
  font-size: 13px;
  margin: 8px 0 20px;
}
</style>
