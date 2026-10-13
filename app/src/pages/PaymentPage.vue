<template>
  <q-page class="app-page">
    <PageHeader :title="$t('payment.title')" fallback="/plans" />

    <StatusPanel :loading="loading" :error="error" :retry="load">
      <template v-if="plan">
        <div class="card summary">
          <span>{{ $t('plans.subscription', { n: formatNumber(plan.months) }) }}</span>
          <strong class="amount">{{ amountText }}</strong>
        </div>

        <q-banner v-if="pending" class="bg-warning text-dark q-mt-md" rounded dense>{{
          $t('payment.alreadyPending')
        }}</q-banner>

        <h2>{{ $t('payment.method') }}</h2>
        <div class="methods" role="tablist">
          <button
            v-for="m in subscription.methods"
            :key="m.id"
            type="button"
            role="tab"
            class="method"
            :class="{ on: methodId === m.id }"
            @click="methodId = m.id"
          >
            {{ m.title }}
          </button>
        </div>

        <div v-if="method" class="card instructions">
          <p class="muted">{{ method.instructions }}</p>
          <div v-if="copyText" class="copy" dir="ltr">
            <span class="code">{{ copyText }}</span>
            <q-btn
              flat
              round
              dense
              icon="content_copy"
              color="primary"
              :aria-label="$t('payment.copy')"
              @click="copy"
            />
          </div>
          <div v-if="qr" class="qr" v-html="qr" />
          <p v-if="method.type === 'crypto'" class="warn">{{ $t('payment.trc20') }}</p>
        </div>

        <form novalidate @submit.prevent="send">
          <q-banner v-if="message" class="bg-negative text-white q-my-md" rounded dense>{{
            message
          }}</q-banner>
          <BaseInput
            v-model="form.tracking_code"
            :label="$t('payment.tracking')"
            :placeholder="$t('payment.trackingExample')"
            dir="ltr"
            :error="errors.tracking_code"
          />

          <label class="drop" :class="{ filled: file }">
            <input
              class="file"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              @change="onFile"
            />
            <template v-if="!file">
              <q-icon name="upload" size="32px" color="primary" />
              <strong>{{ $t('payment.upload') }}</strong>
              <span class="muted small">{{ $t('payment.uploadHint') }}</span>
            </template>
            <template v-else>
              <img v-if="preview" class="preview" :src="preview" alt="" />
              <span class="small">{{ file.name }}</span>
            </template>
          </label>
          <div v-if="errors.receipt" class="text-negative small">{{ errors.receipt }}</div>

          <q-btn
            class="btn q-mt-md"
            type="submit"
            color="primary"
            text-color="dark"
            unelevated
            no-caps
            :label="$t('payment.submit')"
            :loading="submitting"
            :disable="!method || !file"
          />
          <p class="muted note">{{ $t('payment.note') }}</p>
        </form>
      </template>
    </StatusPanel>
  </q-page>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import BaseInput from '@/components/BaseInput.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusPanel from '@/components/StatusPanel.vue'
import { api } from '@/boot/axios'
import { useApiForm } from '@/composables/useApiForm'
import { useAsync } from '@/composables/useAsync'
import { useSubscriptionStore } from '@/stores/useSubscriptionStore'
import { formatNumber } from '@shared/utils/number.js'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const subscription = useSubscriptionStore()
const form = reactive({ tracking_code: '' })
const file = ref(null)
const preview = ref('')
const methodId = ref(null)
const qr = ref('')
const { loading: submitting, errors, message, submit } = useApiForm()

const { loading, error, run } = useAsync(
  () =>
    Promise.all([
      subscription.fetchPlans(),
      subscription.fetchMethods(),
      subscription.fetchPayments(),
    ]),
  { fallbackMessage: t('common.genericError') },
)

const plan = computed(() => subscription.plans.find((p) => p.id === Number(route.query.plan)))
const method = computed(() => subscription.methods.find((m) => m.id === methodId.value))
const pending = computed(() => subscription.payments.some((p) => p.status === 'pending'))

const amountText = computed(() => {
  const price = plan.value?.prices.find((p) => p.currency === method.value?.currency)
  return price
    ? `${formatNumber(Math.round(price.amount))} ${t(`currency.${price.currency}`)}`
    : t('payment.noPrice')
})

// First long digit run in the instructions (a card number / wallet) can be copied with one tap.
const copyText = computed(() => {
  const text = method.value?.instructions ?? ''
  const card = text.match(/\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}/)
  if (card) return card[0]
  return text.match(/\bT[1-9A-HJ-NP-Za-km-z]{33}\b/)?.[0] ?? ''
})

async function copy() {
  await navigator.clipboard?.writeText(copyText.value.replace(/[\s-]/g, ''))
  Notify.create({ message: t('common.copied'), type: 'positive', timeout: 1500 })
}

function onFile(event) {
  file.value = event.target.files[0] ?? null
  if (preview.value) URL.revokeObjectURL(preview.value)
  preview.value = file.value ? URL.createObjectURL(file.value) : ''
}

// Crypto methods show the same QR the support page uses (made by the API).
async function loadQr() {
  qr.value = ''
  if (method.value?.type !== 'crypto' || !copyText.value) return
  try {
    const { data } = await api.get('/api/support')
    qr.value = data.data.find((item) => item.value === copyText.value)?.qr_svg ?? ''
  } catch {
    qr.value = ''
  }
}

async function send() {
  const ok = await submit(() =>
    subscription.submitPayment({
      plan_id: plan.value.id,
      payment_method_id: method.value.id,
      tracking_code: form.tracking_code,
      receipt: file.value,
    }),
  )
  if (ok) router.replace({ name: 'payments', query: { submitted: 1 } })
}

onMounted(async () => {
  await run()
  methodId.value = subscription.methods[0]?.id ?? null
  loadQr()
})
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value))
</script>

<style scoped lang="scss">
.summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 16px;
}

.amount {
  color: var(--primary);
}

h2 {
  font-size: 16px;
  margin: 22px 0 10px;
}

.methods {
  display: flex;
  gap: 6px;
  padding: 6px;
  background: var(--surface);
  border-radius: 18px;
}

.method {
  flex: 1;
  height: 44px;
  border: 0;
  border-radius: 14px;
  background: transparent;
  color: var(--muted);
  font-family: inherit;
  font-size: 15px;
  cursor: pointer;

  &.on {
    background: var(--primary);
    color: $dark-page;
    font-weight: 700;
  }
}

.instructions {
  margin: 16px 0;
  border: 1px solid var(--line);
  text-align: center;

  p {
    margin: 0 0 8px;
    line-height: 2;
    white-space: pre-line;
  }
}

.copy {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 20px;
  font-weight: 700;
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
}

.drop {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  margin-top: 18px;
  padding: 24px 12px;
  border: 2px dashed var(--primary);
  border-radius: 20px;
  text-align: center;
  cursor: pointer;
}

.file {
  position: absolute;
  opacity: 0;
  width: 1px;
  height: 1px;
}

.preview {
  max-height: 160px;
  border-radius: 12px;
}

.small {
  font-size: 12px;
}

.note {
  text-align: center;
  font-size: 13px;
  line-height: 2;
  margin-top: 16px;
}
</style>
