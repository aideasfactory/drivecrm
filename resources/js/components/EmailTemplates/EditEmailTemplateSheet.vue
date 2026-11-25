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
import { Checkbox } from '@/components/ui/checkbox'
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { toast } from '@/components/ui/sonner'
import {
    AlertTriangle,
    Eye,
    Loader2,
    Mail,
    RotateCcw,
    Save,
    Share2,
    UserRound,
    Wand2,
} from 'lucide-vue-next'
import EmailPreviewDialog from '@/components/EmailTemplates/EmailPreviewDialog.vue'
import EmailFocusedEditor from '@/components/EmailTemplates/EmailFocusedEditor.vue'
import EmailTokenInput from '@/components/EmailTemplates/EmailTokenInput.vue'
import { parseEmailBlocks } from '@/lib/emailBlocks'
import type { WorkflowReference } from '@/types/workflow'
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
    /** Workflows that send this email. */
    used_in: WorkflowReference[]
}

interface Props {
    open: boolean
    template: EmailTemplateItem | null
    /** The workflow the email was opened from, if any. */
    workflowTitle?: string | null
    /** Conditions that always hold in that workflow. */
    scenario?: string[]
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'saved'): void
}

const props = withDefaults(defineProps<Props>(), {
    workflowTitle: null,
    scenario: () => [],
})
const emit = defineEmits<Emits>()

const saving = ref(false)
const isPreviewOpen = ref(false)
const restoring = ref(false)
const errors = reactive<Record<string, string>>({})
const reader = ref<string[]>([])
type TokenInput = InstanceType<typeof EmailTokenInput>

const activeInput = ref<TokenInput | null>(null)
const subjectInput = ref<TokenInput | null>(null)
const greetingInput = ref<TokenInput | null>(null)
const salutationInput = ref<TokenInput | null>(null)

const form = reactive({
    subject: '',
    greeting: '',
    body: '',
    salutation: '',
    action_text: '',
})

watch(
    () => [props.template, props.open] as const,
    ([template, open]) => {
        if (!template || !open) {
            return
        }

        reader.value = props.scenario.filter((key) =>
            template.conditions.some((condition) => condition.key === key),
        )
        activeInput.value = null

        form.subject = template.subject
        form.greeting = template.greeting
        form.body = template.body
        form.salutation = template.salutation
        form.action_text = template.action_text ?? ''
        Object.keys(errors).forEach((key) => delete errors[key])
    },
    { immediate: true },
)

/**
 * Owners only change wording. Who sees which paragraph is set up by the team,
 * so an email whose layout can't be read (unbalanced "if" markers) is locked.
 */
const canEdit = computed(() => parseEmailBlocks(form.body) !== null)

/** Every "if / otherwise / end" marker still has its partner. */
const markersIntact = (): boolean =>
    [form.subject, form.greeting, form.body, form.salutation].every((text) => parseEmailBlocks(text) !== null)

const conditionLabel = (key: string): string =>
    props.template?.conditions.find((condition) => condition.key === key)?.label ?? key

const readerSummary = computed(() =>
    reader.value.length ? reader.value.map(conditionLabel).join(' · ') : 'Most learners (none of the special cases)',
)

const placeholderLabels = computed<Record<string, string>>(() =>
    Object.fromEntries((props.template?.placeholders ?? []).map((placeholder) => [placeholder.key, placeholder.label])),
)

const otherWorkflows = computed(() =>
    (props.template?.used_in ?? []).filter((workflow) => workflow.title !== props.workflowTitle),
)

const toggleReader = (key: string, enabled: boolean | 'indeterminate') => {
    reader.value =
        enabled === true ? [...reader.value, key] : reader.value.filter((condition) => condition !== key)
}

const insertIntoActive = (key: string) => {
    if (!activeInput.value) {
        toast.info('Click into the text where the placeholder should go first.')

        return
    }

    activeInput.value.insertToken(key)
}

const canSave = computed(
    () => form.subject.trim().length > 0 && form.body.trim().length > 0,
)

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

    if (!markersIntact()) {
        toast.error(
            'A wording marker ("if", "otherwise" or "end") was removed. Undo that change, or use Restore default.',
        )

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
        <SheetContent class="overflow-y-auto sm:max-w-2xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Mail class="h-5 w-5" />
                    {{ template?.name ?? 'Edit email' }}
                </SheetTitle>
                <SheetDescription>
                    {{ template?.description }}
                </SheetDescription>
            </SheetHeader>

            <TooltipProvider>
                <form
                    v-if="template"
                    @submit.prevent="handleSave"
                    class="space-y-6 px-6 py-4"
                >
                    <!-- Who this view is for, and where else the email goes -->
                    <div class="space-y-2 rounded-md border bg-muted/30 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex min-w-0 items-start gap-2">
                                <UserRound class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                <div class="min-w-0">
                                    <p class="text-xs text-muted-foreground">
                                        You are editing what these learners read
                                    </p>
                                    <p class="text-sm font-medium">{{ readerSummary }}</p>
                                </div>
                            </div>
                            <Popover v-if="template.conditions.length">
                                <PopoverTrigger as-child>
                                    <Button type="button" variant="outline" size="sm" class="cursor-pointer">
                                        Change
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent align="end" class="w-80 space-y-2">
                                    <p class="text-sm font-medium">Which learner?</p>
                                    <p class="text-xs text-muted-foreground">
                                        Tick what applies to them. Leave all unticked for most learners.
                                    </p>
                                    <div
                                        v-for="condition in template.conditions"
                                        :key="condition.key"
                                        class="flex items-start gap-2"
                                    >
                                        <Checkbox
                                            :id="`reader-${condition.key}`"
                                            :model-value="reader.includes(condition.key)"
                                            class="mt-0.5 cursor-pointer"
                                            @update:model-value="toggleReader(condition.key, $event)"
                                        />
                                        <Label
                                            :for="`reader-${condition.key}`"
                                            class="cursor-pointer text-xs font-normal leading-snug"
                                        >
                                            {{ condition.label }}
                                        </Label>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>
                        <Tooltip v-if="template.used_in.length > 1">
                            <TooltipTrigger as-child>
                                <p class="flex w-fit cursor-help items-center gap-1.5 text-xs text-muted-foreground">
                                    <Share2 class="h-3 w-3" />
                                    Text without an "Only some learners" tag also goes out in
                                    {{ workflowTitle ? otherWorkflows.length : template.used_in.length }}
                                    {{ workflowTitle ? 'other' : '' }}
                                    {{ (workflowTitle ? otherWorkflows.length : template.used_in.length) === 1 ? 'workflow' : 'workflows' }}
                                </p>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-sm">
                                <p
                                    v-for="workflow in workflowTitle ? otherWorkflows : template.used_in"
                                    :key="workflow.key"
                                >
                                    {{ workflow.title }}
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </div>

                    <Alert v-if="!canEdit" variant="destructive">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>This email can't be edited here</AlertTitle>
                        <AlertDescription>
                            Its layout needs fixing by the team. Use "Restore default" to go back to the standard
                            wording, or ask us to look at it.
                        </AlertDescription>
                    </Alert>

                    <template v-else>
                        <div class="space-y-2">
                            <Label>Subject</Label>
                            <EmailTokenInput
                                ref="subjectInput"
                                v-model="form.subject"
                                :labels="placeholderLabels"
                                :multiline="false"
                                @focused="activeInput = subjectInput"
                            />
                            <p v-if="errors.subject" class="text-sm text-destructive">{{ errors.subject }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Greeting</Label>
                            <EmailTokenInput
                                ref="greetingInput"
                                v-model="form.greeting"
                                :labels="placeholderLabels"
                                :multiline="false"
                                @focused="activeInput = greetingInput"
                            />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <Label>Email</Label>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button type="button" variant="outline" size="sm" class="cursor-pointer">
                                            <Wand2 class="mr-1 h-3.5 w-3.5" />
                                            Insert detail
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="max-h-80 w-80 overflow-y-auto">
                                        <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">
                                            Added where your cursor is. Filled in when the email sends.
                                        </DropdownMenuLabel>
                                        <DropdownMenuItem
                                            v-for="placeholder in template.placeholders"
                                            :key="placeholder.key"
                                            class="cursor-pointer flex-col items-start gap-0.5"
                                            @mousedown.prevent
                                            @click="insertIntoActive(placeholder.key)"
                                        >
                                            <span class="text-sm">{{ placeholder.label }}</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <EmailFocusedEditor
                                v-model="form.body"
                                :conditions="template.conditions"
                                :placeholders="template.placeholders"
                                :reader="reader"
                                :action-text="form.action_text"
                                @focus-input="activeInput = $event"
                            />
                            <p v-if="errors.body" class="text-sm text-destructive">{{ errors.body }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="action_text">Button text</Label>
                                <Input id="action_text" v-model="form.action_text" placeholder="No button" />
                            </div>
                            <div class="space-y-2">
                                <Label>Sign-off</Label>
                                <EmailTokenInput
                                    ref="salutationInput"
                                    v-model="form.salutation"
                                    :labels="placeholderLabels"
                                    @focused="activeInput = salutationInput"
                                />
                            </div>
                        </div>

                    </template>

                    <p class="text-xs text-muted-foreground">
                        Changing the wording never changes who gets this email or when it's sent.
                    </p>

                    <div class="flex flex-wrap justify-end gap-3 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="saving || restoring"
                            class="cursor-pointer"
                            @click="handleRestore"
                        >
                            <Loader2 v-if="restoring" class="mr-2 h-4 w-4 animate-spin" />
                            <RotateCcw v-else class="mr-2 h-4 w-4" />
                            Restore default
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="saving || restoring"
                            class="cursor-pointer"
                            @click="isPreviewOpen = true"
                        >
                            <Eye class="mr-2 h-4 w-4" />
                            Preview
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
                        <Button type="submit" :disabled="saving || restoring || !canSave || !canEdit" class="cursor-pointer min-w-[120px]">
                            <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                            <Save v-else class="mr-2 h-4 w-4" />
                            {{ saving ? 'Saving...' : 'Save' }}
                        </Button>
                    </div>
                </form>
            </TooltipProvider>

            <EmailPreviewDialog
                :open="isPreviewOpen"
                :template="template"
                :copy="form"
                :initial-conditions="reader"
                @update:open="isPreviewOpen = $event"
            />
        </SheetContent>
    </Sheet>
</template>
