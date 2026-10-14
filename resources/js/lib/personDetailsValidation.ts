/**
 * Client-side mirror of App\Support\PersonDetails (server rules). Keep both in sync.
 * Each validator returns an error message, or null when the value is valid.
 */

const NAME_PATTERN = /^\p{L}[\p{L}\p{M}\s'’-]*$/u
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const UK_POSTCODE_PATTERN = /^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i

export function validateName(value: string | null | undefined, label: 'First name' | 'Last name', requiredMessage: string): string | null {
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

export function validateEmailAddress(value: string | null | undefined, requiredMessage: string): string | null {
  const email = (value ?? '').trim()

  if (!email) {
    return requiredMessage
  }

  if (email.length > 255 || !EMAIL_PATTERN.test(email)) {
    return 'Enter a valid email address, e.g. name@example.com'
  }

  return null
}

/**
 * Normalise a UK mobile to E.164 (+447XXXXXXXXX), ignoring spaces, dashes and
 * brackets. Accepts 07…, 7…, 447…, +447… and "+44 07…". Returns null if invalid.
 */
export function normaliseUkMobile(value: string | null | undefined): string | null {
  const stripped = (value ?? '').replace(/[\s\-().]/g, '')
  const match = stripped.match(/^(?:(?:\+|00)?440?|0)?(7\d{9})$/)

  return match ? `+44${match[1]}` : null
}

export const UK_MOBILE_ERROR = 'Enter a valid UK mobile number starting 07 or +447, e.g. 07700 900123'

export function validateUkMobile(value: string | null | undefined, requiredMessage: string): string | null {
  if (!(value ?? '').trim()) {
    return requiredMessage
  }

  return normaliseUkMobile(value) ? null : UK_MOBILE_ERROR
}

function toIsoDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

export function todayIsoDate(): string {
  return toIsoDate(new Date())
}

/** Latest date of birth that still meets the minimum age today (matches Carbon's subYears overflow). */
export function latestDateOfBirth(minimumAge: number): string {
  const today = new Date()

  return toIsoDate(new Date(today.getFullYear() - minimumAge, today.getMonth(), today.getDate()))
}

/**
 * Dates come from <input type="date">, which always yields YYYY-MM-DD (or ''
 * when incomplete), so they are compared as strings to avoid UTC parsing shifts.
 */
export function validateDateOfBirth(value: string | null | undefined, minimumAge: number, requiredMessage: string): string | null {
  const dob = (value ?? '').trim()

  if (!dob) {
    return requiredMessage
  }

  const match = dob.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  const parsed = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null

  if (!parsed || toIsoDate(parsed) !== dob) {
    return 'Enter a valid date of birth'
  }

  if (dob > todayIsoDate()) {
    return 'Date of birth cannot be in the future'
  }

  if (dob > latestDateOfBirth(minimumAge)) {
    return `The learner must be at least ${minimumAge} years old to book lessons`
  }

  return null
}

export function validateUkPostcode(value: string | null | undefined): string | null {
  const postcode = (value ?? '').trim()

  if (!postcode) {
    return 'Please enter your postcode'
  }

  return UK_POSTCODE_PATTERN.test(postcode) ? null : 'Please enter a valid UK postcode'
}

/**
 * National-number input paired with a dial-code <select>. Returns the digits to
 * append to the dial code, forgiving a typed leading 0 and, for +44, a typed
 * "44"/"+44" prefix (e.g. "+44 07700 900123" → "7700900123").
 */
export function nationalDigits(phone: string, dialCode: string): string {
  if (dialCode === '+44') {
    const uk = normaliseUkMobile(phone)

    if (uk) {
      return uk.slice(3)
    }
  }

  const digits = phone.replace(/\D/g, '')

  return digits.startsWith('0') ? digits.slice(1) : digits
}

export function validateDialCodePhone(phone: string | null | undefined, dialCode: string): string | null {
  if (!(phone ?? '').trim()) {
    return 'Please enter your mobile number'
  }

  if (dialCode === '+44') {
    return normaliseUkMobile(phone) ? null : UK_MOBILE_ERROR
  }

  const totalLength = dialCode.replace(/\D/g, '').length + nationalDigits(phone ?? '', dialCode).length

  return totalLength >= 8 && totalLength <= 15 ? null : 'Enter a valid mobile number for the selected country'
}
