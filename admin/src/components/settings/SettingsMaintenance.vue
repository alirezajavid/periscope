<template>
  <q-form class="column q-gutter-md" @submit.prevent="submit">
    <q-banner v-if="message" class="bg-negative text-white" rounded dense>{{ message }}</q-banner>
    <q-toggle v-model="draft.maintenance" :label="$t('settings.maintenance')" />
    <q-input
      v-model="draft.maintenance_message"
      outlined
      type="textarea"
      autogrow
      :label="$t('settings.maintenanceMessage')"
      :error="!!errors.maintenance_message"
      :error-message="errors.maintenance_message"
    />
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
import { reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiForm } from '@/composables/useApiForm'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
  settings: { type: Object, required: true },
  save: { type: Function, required: true },
})

const { t } = useI18n()
const confirm = useConfirm()
const { loading, errors, message, submit: send } = useApiForm()

const wasOn = !!props.settings.maintenance
const draft = reactive({
  maintenance: wasOn,
  maintenance_message: props.settings.maintenance_message ?? '',
})

async function submit() {
  if (draft.maintenance && !wasOn) {
    const ok = await confirm({ message: t('settings.maintenanceConfirm'), label: t('common.save') })
    if (!ok) return
  }
  send(() => props.save({ ...draft, maintenance_message: draft.maintenance_message || null }))
}
</script>
