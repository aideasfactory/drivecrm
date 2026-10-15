<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { toast } from '@/components/ui/sonner'
import { Mail, Loader2, Save, RotateCcw, Plus } from 'lucide-vue-next'
import {
    update as updateEmailTemplate,
    restore as restoreEmailTemplate,
} from '@/routes/email-templates'

export interface EmailTemplatePlaceholder {
    key: string
    label: string
}

export interface EmailTemplateItem {
    key: string
    name: string
    audience: 'learner' | 'instructor' | 'both' | 'admin'
    audience_label: string
    description: string
    placeholders: EmailTemplatePlaceholder[]
    conditions: EmailTemplatePlaceholder[]
    subject: string
    greeting: string
    body: string
    salutation: string
    action_text: string | null
    is_customised: boolean
    updated_at: string | null
}

interface Props {
    open: boolean
    template: EmailTemplateItem | null
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const saving = ref(false)
const restoring = ref(false)
const errors = reactive<Record<string, string>>({})

const form = reactive({
    subject: '',
    greeting: '',
    body: '',
    salutation: '',
    action_text: '',
})

watch(
    () => props.template,
    (template) => {
        if (!template) {
            return
        }

        form.subject = template.subject
        form.greeting = template.greeting
        form.body = template.body
        form.salutation = template.salutation
        form.action_text = template.action_text ?? ''
        lastFocus.field = 'body'
        lastFocus.start = null
        lastFocus.end = null
        Object.keys(errors).forEach((key) => delete errors[key])
    },
    { immediate: true },
)

const canSave = computed(
    () => form.subject.trim().length > 0 && form.body.trim().length > 0,
)

type EditableField = 'subject' | 'greeting' | 'body' | 'salutation'

const lastFocus = reactive<{
    field: EditableField
    start: number | null
    end: number | null
}>({ field: 'body', start: null, end: null })

const rememberCursor = (field: EditableField, event: FocusEvent) => {
    const target = event.target as HTMLInputElement | HTMLTextAreaElement
    lastFocus.field = field
    lastFocus.start = target.selectionStart
    lastFocus.end = target.selectionEnd
}

const insertText = (text: string) => {
    const field = lastFocus.field
    const current = form[field] ?? ''
    const start = lastFocus.start ?? current.length
    const end = lastFocus.end ?? start

    form[field] = `${current.slice(0, start)}${text}${current.slice(end)}`
    lastFocus.start = start + text.length
    lastFocus.end = lastFocus.start
}

const insertPlaceholder = (key: string) => {
    insertText(`{{${key}}}`)
}

const insertCondition = (key: string) => {
    const field = lastFocus.field
    const current = form[field] ?? ''
    const start = lastFocus.start ?? current.length
    const end = lastFocus.end ?? start
    const selected = current.slice(start, end)

    insertText(`{{#if ${key}}}${selected}{{/if}}`)
}

const applyValidationErrors = (validationErrors: Record<string, string>) => {
    Object.keys(errors).forEach((key) => delete errors[key])
    Object.entries(validationErrors).forEach(([key, message]) => {
        errors[key] = Array.isArray(message) ? message[0] : message
    })

    const firstError = Object.values(errors)[0]
    toast.error(firstError || 'Please fix the highlighted errors')
}

const handleSave = () => {
    if (!props.template || !canSave.value) {
        return
    }

    saving.value = true
    Object.keys(errors).forEach((key) => delete errors[key])

    router.put(
        updateEmailTemplate.url(props.template.key),
        {
            subject: form.subject,
            greeting: form.greeting,
            body: form.body,
            salutation: form.salutation,
            action_text: form.action_text,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Email template saved.')
                emit('saved')
                emit('update:open', false)
            },
            onError: (validationErrors) => {
                applyValidationErrors(validationErrors as Record<string, string>)
            },
            onFinish: () => {
                saving.value = false
            },
        },
    )
}

const handleRestore = () => {
    if (!props.template) {
        return
    }

    restoring.value = true

    router.post(
        restoreEmailTemplate.url(props.template.key),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Email template restored to the default copy.')
                emit('saved')
                emit('update:open', false)
            },
            onError: () => {
                toast.error('Failed to restore default copy')
            },
            onFinish: () => {
                restoring.value = false
            },
        },
    )
}

const handleOpenChange = (value: boolean) => {
    if (!saving.value && !restoring.value) {
        emit('update:open', value)
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="handleOpenChange">
        <SheetContent class="overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Mail class="h-5 w-5" />
                    {{ template?.name ?? 'Edit email' }}
                </SheetTitle>
                <SheetDescription>
                    {{ template?.description }}
                    Changing this copy does not change who receives the email
                    or when it is sent.
                </SheetDescription>
            </SheetHeader>

            <form
                v-if="template"
                @submit.prevent="handleSave"
                class="mt-6 space-y-6 px-6 py-4"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {{ template.audience_label }}
                    </Badge>
                    <Badge v-if="template.is_customised" variant="outline">
                        Customised
                    </Badge>
                </div>

                <div class="space-y-2">
                    <Label for="subject">Subject</Label>
                    <Input
                        id="subject"
                        v-model="form.subject"
                        @blur="rememberCursor('subject', $event)"
                    />
                    <p v-if="errors.subject" class="text-sm text-destructive">
                        {{ errors.subject }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="greeting">Greeting</Label>
                    <Input
                        id="greeting"
                        v-model="form.greeting"
                        placeholder="Hello {{recipient_name}},"
                        @blur="rememberCursor('greeting', $event)"
                    />
                    <p v-if="errors.greeting" class="text-sm text-destructive">
                        {{ errors.greeting }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="body">Body</Label>
                    <Textarea
                        id="body"
                        v-model="form.body"
                        class="min-h-64 font-mono text-sm"
                        @blur="rememberCursor('body', $event)"
                    />
                    <p class="text-xs text-muted-foreground">
                        Markdown is supported (**bold**, lists, links). Put
                        <span v-text="'{{action_button}}'" /> where the button
                        should appear.
                    </p>
                    <p v-if="errors.body" class="text-sm text-destructive">
                        {{ errors.body }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Available placeholders</Label>
                    <p class="text-xs text-muted-foreground">
                        Click a placeholder to insert it where your cursor was
                        in the subject, greeting, body or sign-off. Values are
                        filled in when the email sends. Placeholders not listed
                        here are left blank, so a typo will never break an
                        email.
                    </p>
                    <ul class="divide-y rounded-md border">
                        <li
                            v-for="placeholder in template.placeholders"
                            :key="placeholder.key"
                            class="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div class="min-w-0">
                                <code
                                    class="text-xs font-medium"
                                    v-text="`{{${placeholder.key}}}`"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ placeholder.label }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="shrink-0 cursor-pointer"
                                @click="insertPlaceholder(placeholder.key)"
                            >
                                <Plus class="mr-1 h-3 w-3" />
                                Insert
                            </Button>
                        </li>
                    </ul>
                </div>

                <div v-if="template.conditions.length" class="space-y-2">
                    <Label>Conditions</Label>
                    <p class="text-xs text-muted-foreground">
                        Show text only in some cases with
                        <code
                            v-text="'{{#if condition}}shown when true{{else}}shown otherwise{{/if}}'"
                        />. The
                        <code v-text="'{{else}}'" /> part is optional. Any
                        placeholder above also works as a condition (true when
                        it has a value). Select text first to wrap it.
                    </p>
                    <ul class="divide-y rounded-md border">
                        <li
                            v-for="condition in template.conditions"
                            :key="condition.key"
                            class="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div class="min-w-0">
                                <code
                                    class="text-xs font-medium"
                                    v-text="condition.key"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ condition.label }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="shrink-0 cursor-pointer"
                                @click="insertCondition(condition.key)"
                            >
                                <Plus class="mr-1 h-3 w-3" />
                                Insert
                            </Button>
                        </li>
                    </ul>
                </div>

                <div class="space-y-2">
                    <Label for="action_text">Button label</Label>
                    <Input
                        id="action_text"
                        v-model="form.action_text"
                        placeholder="Leave blank for no button"
                    />
                    <p
                        v-if="errors.action_text"
                        class="text-sm text-destructive"
                    >
                        {{ errors.action_text }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        The button URL is always generated by the sending
                        process and cannot be edited here.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="salutation">Sign-off</Label>
                    <Textarea
                        id="salutation"
                        v-model="form.salutation"
                        class="min-h-20"
                        @blur="rememberCursor('salutation', $event)"
                    />
                    <p
                        v-if="errors.salutation"
                        class="text-sm text-destructive"
                    >
                        {{ errors.salutation }}
                    </p>
                </div>

                <div class="flex flex-wrap justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="saving || restoring"
                        class="cursor-pointer"
                        @click="handleRestore"
                    >
                        <Loader2
                            v-if="restoring"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <RotateCcw v-else class="mr-2 h-4 w-4" />
                        Restore default
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="saving || restoring"
                        class="cursor-pointer"
                        @click="handleOpenChange(false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="saving || restoring || !canSave"
                        class="cursor-pointer min-w-[120px]"
                    >
                        <Loader2
                            v-if="saving"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{ saving ? 'Saving...' : 'Save' }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
