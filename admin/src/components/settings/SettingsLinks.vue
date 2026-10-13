<template>
  <q-form class="column q-gutter-lg" @submit.prevent="submit">
    <q-banner v-if="message" class="bg-negative text-white" rounded dense>{{ message }}</q-banner>

    <section>
      <div class="text-subtitle1 q-mb-sm">{{ $t('settings.social') }}</div>
      <div v-for="(link, index) in social" :key="index" class="item-row">
        <q-input
          v-model="link.title"
          outlined
          dense
          :label="$t('settings.linkTitle')"
          :error="!!errors[`social_links.${index}.title`]"
          :error-message="errors[`social_links.${index}.title`]"
        />
        <q-input
          v-model="link.url"
          outlined
          dense
          dir="ltr"
          :label="$t('settings.url')"
          :error="!!errors[`social_links.${index}.url`]"
          :error-message="errors[`social_links.${index}.url`]"
        />
        <q-btn
          flat
          round
          dense
          color="negative"
          icon="delete"
          :aria-label="$t('settings.remove')"
          @click="social.splice(index, 1)"
        />
      </div>
      <q-btn
        flat
        no-caps
        color="primary"
        icon="add"
        :label="$t('settings.add')"
        @click="social.push({ title: '', url: '' })"
      />
    </section>

    <section>
      <div class="text-subtitle1 q-mb-sm">{{ $t('settings.support') }}</div>
      <q-card v-for="(item, index) in support" :key="index" flat bordered class="q-mb-sm">
        <q-card-section class="support-grid">
          <q-input
            v-model="item.title"
            outlined
            dense
            :label="$t('settings.linkTitle')"
            :error="!!errors[`support_items.${index}.title`]"
            :error-message="errors[`support_items.${index}.title`]"
          />
          <q-select
            v-model="item.kind"
            outlined
            dense
            emit-value
            map-options
            :options="kindOptions"
            :label="$t('settings.kind')"
          />
          <q-input
            v-model="item.value"
            outlined
            dense
            dir="ltr"
            :label="$t('settings.value')"
            :error="!!errors[`support_items.${index}.value`]"
            :error-message="errors[`support_items.${index}.value`]"
          />
          <q-input
            v-if="item.kind === 'crypto'"
            v-model="item.network"
            outlined
            dense
            dir="ltr"
            :label="$t('settings.network')"
          />
          <q-input v-model="item.note" outlined dense :label="$t('settings.note')" />
          <div>
            <q-btn
              flat
              dense
              no-caps
              color="negative"
              icon="delete"
              :label="$t('settings.remove')"
              @click="support.splice(index, 1)"
            />
          </div>
        </q-card-section>
      </q-card>
      <q-btn
        flat
        no-caps
        color="primary"
        icon="add"
        :label="$t('settings.add')"
        @click="support.push({ title: '', kind: 'link', value: '', network: '', note: '' })"
      />
    </section>

    <div>
      <q-btn
        type="submit"
        color="primary"
        unelevated
        no-caps
        :label="$t('common.save')"
        :loading="loading"
      />
    </div>
  </q-form>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiForm } from '@/composables/useApiForm'

const props = defineProps({
  settings: { type: Object, required: true },
  save: { type: Function, required: true },
})

const { t } = useI18n()
const { loading, errors, message, submit: send } = useApiForm()

const social = ref((props.settings.social_links ?? []).map((l) => ({ ...l })))
const support = ref(
  (props.settings.support_items ?? []).map((i) => ({ network: '', note: '', ...i })),
)
const kindOptions = computed(() =>
  ['link', 'crypto'].map((value) => ({ value, label: t(`settings.kindValue.${value}`) })),
)

const submit = () =>
  send(() =>
    props.save({
      social_links: social.value,
      support_items: support.value.map((item) => ({
        ...item,
        network: item.kind === 'crypto' ? item.network || null : null,
        note: item.note || null,
      })),
    }),
  )
</script>

<style scoped>
.item-row {
  display: grid;
  grid-template-columns: 1fr 2fr auto;
  gap: 8px;
  align-items: start;
  margin-bottom: 8px;
}
.support-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
  gap: 8px;
}
@media (max-width: 600px) {
  .item-row {
    grid-template-columns: 1fr auto;
  }
}
</style>
