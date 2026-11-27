<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

/**
 * A text field that shows `{{placeholders}}` as readable chips ("Instructor
 * name") and saves plain text with the placeholders written out, exactly as
 * the email body stores them. Conditional tags inside a sentence
 * (`{{#if …}}`) show as small read-only markers.
 */

interface Props {
    modelValue: string
    /** Placeholder key → full description, shown on hover. */
    labels?: Record<string, string>
    multiline?: boolean
    placeholder?: string
    readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    labels: () => ({}),
    multiline: true,
    placeholder: '',
    readonly: false,
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'focused'): void
}>()

const TOKEN = /(\{\{\{?\s*(?:#if\s+[a-z0-9_]+|else|\/if|[a-z0-9_]+)\s*\}?\}\})/i

const root = ref<HTMLElement | null>(null)
let lastEmitted: string | null = null
let savedRange: Range | null = null

const humanise = (key: string): string => {
    const words = key.replace(/_/g, ' ').trim()

    return words.charAt(0).toUpperCase() + words.slice(1)
}

const chip = (raw: string): HTMLElement => {
    const inner = raw.replace(/[{}]/g, '').trim()
    const span = document.createElement('span')

    span.contentEditable = 'false'
    span.dataset.token = raw
    span.className =
        'mx-0.5 inline-flex items-center rounded px-1.5 py-px align-baseline text-xs font-medium select-none'

    if (inner.startsWith('#if')) {
        const key = inner.slice(3).trim()
        span.textContent = `if: ${humanise(key)}`
        span.title = props.labels[key] ?? key
        span.classList.add('border', 'border-dashed', 'text-muted-foreground')
    } else if (inner === 'else' || inner === '/if') {
        span.textContent = inner === 'else' ? 'otherwise' : 'end'
        span.classList.add('border', 'border-dashed', 'text-muted-foreground')
    } else {
        span.textContent = humanise(inner)
        span.title = props.labels[inner.toLowerCase()] ?? inner
        span.classList.add('bg-secondary', 'text-secondary-foreground')
    }

    return span
}

const render = (text: string) => {
    if (!root.value) {
        return
    }

    root.value.replaceChildren(
        ...text
            .split(TOKEN)
            .filter((part) => part !== '')
            // Text between tokens can never contain a whole token, so a match means the part is one.
            .map((part) => (TOKEN.test(part) ? chip(part) : document.createTextNode(part))),
    )
}

/** Read the field back as plain text with placeholders written out. */
const serialise = (node: Node): string => {
    let text = ''

    node.childNodes.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
            text += child.textContent ?? ''

            return
        }

        if (!(child instanceof HTMLElement)) {
            return
        }

        if (child.dataset.token) {
            text += child.dataset.token

            return
        }

        if (child.tagName === 'BR') {
            text += '\n'

            return
        }

        // Some browsers wrap new lines in <div>.
        if (child.tagName === 'DIV' || child.tagName === 'P') {
            text += (text === '' || text.endsWith('\n') ? '' : '\n') + serialise(child)

            return
        }

        text += serialise(child)
    })

    return text
}

const emitValue = () => {
    if (!root.value) {
        return
    }

    let value = serialise(root.value)

    // A trailing <br> only keeps the caret on an empty last line.
    if (value.endsWith('\n') && root.value.lastChild?.nodeName === 'BR') {
        value = value.slice(0, -1)
    }

    lastEmitted = value
    emit('update:modelValue', value)
}

const saveRange = () => {
    const selection = window.getSelection()

    if (selection && selection.rangeCount > 0 && root.value?.contains(selection.anchorNode)) {
        savedRange = selection.getRangeAt(0).cloneRange()
    }
}

const onKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter') {
        return
    }

    event.preventDefault()

    if (props.multiline) {
        document.execCommand('insertLineBreak')
        emitValue()
    }
}

const onPaste = (event: ClipboardEvent) => {
    event.preventDefault()

    let text = event.clipboardData?.getData('text/plain') ?? ''

    if (!props.multiline) {
        text = text.replace(/\s*\n\s*/g, ' ')
    }

    document.execCommand('insertText', false, text)
    emitValue()
}

/** Insert a placeholder where the caret last was (or at the end). */
const insertToken = (key: string) => {
    if (!root.value || props.readonly) {
        return
    }

    const node = chip(`{{${key}}}`)
    const range =
        savedRange && root.value.contains(savedRange.startContainer) ? savedRange : null

    if (range) {
        range.deleteContents()
        range.insertNode(node)
        range.setStartAfter(node)
        range.collapse(true)
        savedRange = range
    } else {
        root.value.appendChild(node)
    }

    emitValue()
}

defineExpose({ insertToken })

onMounted(() => {
    render(props.modelValue)
    lastEmitted = props.modelValue
})

watch(
    () => props.modelValue,
    (value) => {
        if (value !== lastEmitted) {
            render(value)
            lastEmitted = value
        }
    },
)
</script>

<template>
    <div
        ref="root"
        :contenteditable="readonly ? 'false' : 'true'"
        :data-placeholder="placeholder"
        role="textbox"
        :aria-multiline="multiline"
        class="email-token-input w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-relaxed whitespace-pre-wrap break-words shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
        :class="[multiline ? 'min-h-16' : 'min-h-9', readonly ? 'cursor-default bg-muted/40 text-muted-foreground' : '']"
        @input="emitValue"
        @keydown="onKeydown"
        @paste="onPaste"
        @keyup="saveRange"
        @mouseup="saveRange"
        @blur="saveRange"
        @focus="emit('focused')"
    />
</template>

<style scoped>
.email-token-input:empty::before {
    content: attr(data-placeholder);
    color: var(--muted-foreground);
    pointer-events: none;
}
</style>
