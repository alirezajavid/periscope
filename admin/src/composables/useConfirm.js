import { Dialog } from 'quasar'
import { useI18n } from 'vue-i18n'

/** Confirmation dialog for destructive actions; the message must state the consequence. */
export function useConfirm() {
  const { t } = useI18n()

  return function confirm({ title, message, label, color = 'negative' }) {
    return new Promise((resolve) => {
      Dialog.create({
        title: title ?? t('common.confirmTitle'),
        message,
        persistent: true,
        cancel: { label: t('common.cancel'), flat: true },
        ok: { label: label ?? t('common.confirm'), color, unelevated: true },
      })
        .onOk(() => resolve(true))
        .onCancel(() => resolve(false))
        .onDismiss(() => resolve(false))
    })
  }
}
