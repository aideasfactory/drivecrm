import { nextTick } from 'vue'

/*
 * Client-side mirror of App\Support\ContactDetails and the booking form
 * requests. Keep patterns and copy in sync with the server.
 */

// iOS inserts a curly apostrophe (’) by default, so accept both.
const NAME_PATTERN = /^\p{L}[\p{L}\p{M}\s'’-]*$/u
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const UK_MOBILE_PATTERN = /^(?:07\d{9}|\+447\d{9})$/

export type FieldError = string | null

export function validateName(value: string | null | undefined, requiredMessage: string, label: string): FieldError {
  const name = (value ?? '').trim()

  if (!name) {
    return requiredMessage
  }

  if (name.length > 100) {
    return `${label} must be 100 characters or fewer`
  }

  if (!NAME_PATTERN.test(name)) {
    return `${label} can only contain letters, spaces, hyphens and apostrophes`
  }

  return null
}

export function isValidEmail(value: string | null | undefined): boolean {
  return EMAIL_PATTERN.test((value ?? '').trim())
}

export function validateEmail(value: string | null | undefined, requiredMessage: string): FieldError {
  if (!(value ?? '').trim()) {
    return requiredMessage
  }

  return isValidEmail(value) ? null : 'Please enter a valid email address'
}

export function stripWhitespace(value: string | null | undefined): string {
  return (value ?? '').replace(/\s+/gu, '')
}

export function isValidUkMobile(value: string | null | undefined): boolean {
  return UK_MOBILE_PATTERN.test(stripWhitespace(value))
}

export function validateUkMobile(value: string | null | undefined, requiredMessage: string): FieldError {
  if (!stripWhitespace(value)) {
    return requiredMessage
  }

  return isValidUkMobile(value)
    ? null
    : 'Please enter a valid UK mobile number, e.g. 07700 900123 or +44 7700 900123'
}

/**
 * Booker phone typed next to a dial-code picker. With +44 selected the number
 * may be typed as 7…, 07… or +447…; other dial codes take the national number.
 */
export function toE164Phone(phone: string | null | undefined, dialCode: string): string {
  const compact = (phone ?? '').replace(/[\s\-().]/gu, '')

  if (dialCode === '+44') {
    if (/^\+44/.test(compact)) {
      return compact.replace(/^\+440?/, '+44')
    }

    if (/^0?7\d{9}$/.test(compact)) {
      return `+44${compact.replace(/^0/, '')}`
    }

    return compact
  }

  const digits = compact.replace(/\D/g, '').replace(/^0/, '')

  return `${dialCode}${digits}`
}

export function validateBookerPhone(phone: string | null | undefined, dialCode: string): FieldError {
  if (!stripWhitespace(phone)) {
    return 'Please enter your phone number'
  }

  const e164 = toE164Phone(phone, dialCode)

  if (dialCode === '+44') {
    return /^\+447\d{9}$/.test(e164)
      ? null
      : 'Please enter a valid UK mobile number, e.g. 07700 900123'
  }

  return /^\+[1-9]\d{6,14}$/.test(e164) ? null : 'Please enter a valid mobile number'
}

export function validatePostcode(postcode: string | null | undefined): FieldError {
  const value = (postcode ?? '').trim()

  if (!value) {
    return 'Please enter your postcode'
  }

  return /^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i.test(value) ? null : 'Please enter a valid UK postcode'
}

/** Local calendar date as YYYY-MM-DD, matching the value of an <input type="date">. */
export function toIsoDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

export function todayIsoDate(): string {
  return toIsoDate(new Date())
}

export function validateDateOfBirth(
  value: string | null | undefined,
  minimumAge: number,
  requiredMessage: string,
): FieldError {
  const dob = (value ?? '').trim()

  if (!dob) {
    return requiredMessage
  }

  if (!/^\d{4}-\d{2}-\d{2}$/.test(dob) || Number.isNaN(new Date(`${dob}T00:00:00`).getTime())) {
    return 'Please enter a valid date of birth'
  }

  if (dob > todayIsoDate()) {
    return 'Date of birth can’t be in the future'
  }

  const latestAllowed = new Date()
  latestAllowed.setFullYear(latestAllowed.getFullYear() - minimumAge)

  if (dob > toIsoDate(latestAllowed)) {
    return `The learner must be at least ${minimumAge} years old`
  }

  return null
}

/**
 * Focus and scroll to the first field with an error. `fieldIds` maps form
 * attributes to element ids and must be listed in on-screen order.
 *
 * Focus happens synchronously because iOS Safari ignores focus() outside the
 * tap's call stack; the scroll waits a tick so newly rendered error text is
 * accounted for.
 */
export function focusFirstError(
  fieldIds: Record<string, string>,
  errors: Partial<Record<string, string>>,
): void {
  const firstField = Object.keys(fieldIds).find((field) => errors[field])
  const element = firstField ? document.getElementById(fieldIds[firstField]) : null

  if (!element) {
    return
  }

  element.focus({ preventScroll: true })

  nextTick(() => {
    element.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
}
