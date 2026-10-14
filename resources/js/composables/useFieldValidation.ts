import { nextTick, reactive, ref, watch } from 'vue'

export interface ValidatedField {
  /** Key in the Inertia form (and its errors bag). */
  key: string
  /** DOM id of the input to scroll to and focus. */
  id: string
  validate: () => string | null
  /** Fields that are hidden (e.g. learner details when unticked) are skipped. */
  active?: () => boolean
}

interface InertiaFormLike {
  errors: Record<string, string | undefined>
  clearErrors: (...fields: string[]) => void
  [key: string]: any
}

/**
 * Inline validation for Inertia forms: errors appear once a field is blurred
 * or the form is submitted, server errors are shown in the same slot, and the
 * first invalid field is scrolled into view and focused.
 */
export function useFieldValidation(form: InertiaFormLike, fields: ValidatedField[]) {
  const touched = reactive<Record<string, boolean>>({})
  const submitAttempted = ref(false)

  const isActive = (field: ValidatedField) => (field.active ? field.active() : true)
  const findField = (key: string) => fields.find((field) => field.key === key)

  for (const field of fields) {
    watch(
      () => form[field.key],
      () => {
        if (form.errors[field.key]) {
          form.clearErrors(field.key)
        }
      },
    )
  }

  function errorFor(key: string): string | undefined {
    const field = findField(key)

    if (!field || !isActive(field)) {
      return undefined
    }

    if (touched[key] || submitAttempted.value) {
      return field.validate() ?? form.errors[key]
    }

    return form.errors[key]
  }

  function touch(key: string) {
    touched[key] = true
  }

  function focusField(key: string) {
    const field = findField(key)

    if (!field) {
      return
    }

    nextTick(() => {
      const element = document.getElementById(field.id)

      if (!element) {
        return
      }

      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
      element.focus({ preventScroll: true })
    })
  }

  /** Validates every active field; focuses the first invalid one. Returns true when valid. */
  function validateAll(): boolean {
    submitAttempted.value = true

    const firstInvalid = fields.find((field) => isActive(field) && field.validate())

    if (firstInvalid) {
      focusField(firstInvalid.key)

      return false
    }

    return true
  }

  /** Call from an Inertia onError callback to bring the first server error into view. */
  function focusFirstServerError(errors: Record<string, string>) {
    submitAttempted.value = true

    const firstWithError = fields.find((field) => isActive(field) && errors[field.key])

    if (firstWithError) {
      focusField(firstWithError.key)
    }
  }

  function reset() {
    submitAttempted.value = false

    for (const key of Object.keys(touched)) {
      delete touched[key]
    }
  }

  return { errorFor, touch, validateAll, focusFirstServerError, focusField, reset }
}
