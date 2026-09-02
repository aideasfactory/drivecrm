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
import { Mail, Loader2, Save, RotateCcw } from 'lucide-vue-next'
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
    audience: 'learner' | 'instructor' | 'both'
    audience_label: string
    description: string
    placeholders: EmailTemplatePlaceholder[]
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
        Object.keys(errors).forEach((key) => delete errors[key])
    },
    { immediate: true },
)

const canSave = computed(
    () => form.subject.trim().length > 0 && form.body.trim().length > 0,
)

const insertPlaceholder = (key: string) => {
    const token = `{{${key}}}`
    form.body = form.body ? `${form.body}${token}` : token
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
                    <Input id="subject" v-model="form.subject" />
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
                    />
                    <p class="text-xs text-muted-foreground">
                        Markdown is supported (**bold**, lists, links). Put
                        {{ '{{action_button}}' }} where the button should
                        appear.
                    </p>
                    <p v-if="errors.body" class="text-sm text-destructive">
                        {{ errors.body }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Placeholders</Label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="placeholder in template.placeholders"
                            :key="placeholder.key"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer"
                            @click="insertPlaceholder(placeholder.key)"
                        >
                            {{ placeholder.key }}
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Click a placeholder to insert it into the body. Dynamic
                        details (dates, amounts, names) are filled in when the
                        email sends.
                    </p>
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
