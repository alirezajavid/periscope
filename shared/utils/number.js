// Persian digits and price formatting with the built-in Intl API.

const LOCALE = "fa-IR";

/** 1234567 → «۱٬۲۳۴٬۵۶۷» */
export const formatNumber = (value) =>
  new Intl.NumberFormat(LOCALE).format(Number(value));

/** Latin digits in a string → Persian digits ("12:30" → «۱۲:۳۰»). */
export const toPersianDigits = (value) =>
  String(value).replace(/\d/g, (d) => "۰۱۲۳۴۵۶۷۸۹"[d]);

/**
 * Price per currency (the plan_prices units): IRT, USD, USDT.
 * Currency labels are user-facing text, so the caller passes the translated label.
 */
export function formatPrice(amount, label) {
  const number = formatNumber(amount);
  return label ? `${number} ${label}` : number;
}

/** Seconds → «۳۷:۳۵» (or «۱:۰۵:۰۰» past an hour), Persian digits. */
export function formatDuration(totalSeconds) {
  const seconds = Math.max(0, Math.floor(Number(totalSeconds) || 0))
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  const pad = (n) => String(n).padStart(2, '0')
  return toPersianDigits(h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`)
}
