import { computed, onBeforeUnmount, ref } from 'vue'
import { formatNumber } from '@shared/utils/number.js'

/** Counts down from `seconds` once started; used for "resend in 0:45" buttons. */
export function useCountdown(seconds = 60) {
  const remaining = ref(0)
  let timer = null

  function start() {
    remaining.value = seconds
    clearInterval(timer)
    timer = setInterval(() => {
      remaining.value -= 1
      if (remaining.value <= 0) clearInterval(timer)
    }, 1000)
  }

  const active = computed(() => remaining.value > 0)
  const label = computed(() => {
    const minutes = Math.floor(remaining.value / 60)
    const rest = String(remaining.value % 60).padStart(2, '0')
    return `${formatNumber(minutes)}:${rest.replace(/\d/g, (d) => formatNumber(d))}`
  })

  onBeforeUnmount(() => clearInterval(timer))

  return { remaining, active, label, start }
}
