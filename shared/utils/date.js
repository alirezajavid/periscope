// Jalali (Persian calendar) formatting with the built-in Intl API — no date library.

const LOCALE = "fa-IR-u-ca-persian";

function toDate(value) {
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

function format(value, options) {
  const date = toDate(value);
  return date ? new Intl.DateTimeFormat(LOCALE, options).format(date) : "";
}

/** ISO 8601 string or Date → «۲۱ مهر ۱۴۰۵» */
export const formatDate = (value) => format(value, { dateStyle: "long" });

/** → «۲۱ مهر ۱۴۰۵، ۱۴:۳۰» */
export const formatDateTime = (value) =>
  format(value, { dateStyle: "long", timeStyle: "short" });

/** → «۱۴۰۵/۰۷/۲۱» */
export const formatDateShort = (value) =>
  format(value, { year: "numeric", month: "2-digit", day: "2-digit" });
