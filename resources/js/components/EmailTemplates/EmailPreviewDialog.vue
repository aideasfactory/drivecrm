<script setup lang="ts">
import { ref, watch } from 'vue'
import axios from 'axios'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Eye } from 'lucide-vue-next'
import { preview as previewEmailTemplate } from '@/routes/email-templates'
import type { EmailTemplateItem } from '@/components/EmailTemplates/EditEmailTemplateSheet.vue'

export interface EmailPreviewCopy {
    subject: string
    greeting: string
    body: string
    salutation: string
    action_text: string
}

interface Props {
    open: boolean
    template: EmailTemplateItem | null
    copy?: EmailPreviewCopy | null
}

const props = withDefaults(defineProps<Props>(), {
    copy: null,
})

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void
}>()

const loading = ref(false)
const failed = ref(false)
const subject = ref('')
const html = ref('')
const enabledConditions = ref<string[]>([])

const loadPreview = async () => {
    if (!props.template) {
        return
    }

    loading.value = true
    failed.value = false

    try {
        const response = await axios.post(
            previewEmailTemplate.url(props.template.key),
            {
                ...(props.copy ?? {}),
                conditions: enabledConditions.value,
            },
        )
        subject.value = response.data.subject
        html.value = response.data.html
    } catch {
        failed.value = true
    } finally {
        loading.value = false
    }
}

const toggleCondition = (key: string, enabled: boolean | 'indeterminate') => {
    enabledConditions.value = enabled === true
        ? [...enabledConditions.value, key]
        : enabledConditions.value.filter((condition) => condition !== key)
    loadPreview()
}

watch(
    () => [props.open, props.template?.key],
    ([open]) => {
        if (open) {
            enabledConditions.value = []
            html.value = ''
            loadPreview()
        }
    },
)
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[90vh] flex-col gap-4 overflow-hidden sm:max-w-3xl"
        >
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Eye class="h-5 w-5" />
                    {{ template?.name ?? 'Email preview' }}
                </DialogTitle>
                <DialogDescription>
                    How the email looks to {{ template?.audience_label.toLowerCase() ?? 'the recipient' }}.
                    Names, dates and prices are example values, and the
                    button does not go anywhere.
                </DialogDescription>
            </DialogHeader>

            <div class="rounded-md border px-3 py-2 text-sm">
                <span class="text-muted-foreground">Subject: </span>
                <Skeleton v-if="loading && !subject" class="inline-block h-4 w-48 align-middle" />
                <span v-else class="font-medium">{{ subject }}</span>
            </div>

            <div v-if="template?.conditions.length" class="flex flex-col gap-2">
                <p class="text-xs text-muted-foreground">
                    Tick to show the wording for these cases:
                </p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="condition in template.conditions"
                        :key="condition.key"
                        class="flex items-start gap-2"
                    >
                        <Checkbox
                            :id="`preview-condition-${condition.key}`"
                            :model-value="enabledConditions.includes(condition.key)"
                            class="mt-0.5 cursor-pointer"
                            @update:model-value="toggleCondition(condition.key, $event)"
                        />
                        <Label
                            :for="`preview-condition-${condition.key}`"
                            class="cursor-pointer text-xs font-normal leading-snug"
                        >
                            {{ condition.label }}
                        </Label>
                    </div>
                </div>
            </div>

            <div class="relative min-h-0 flex-1 overflow-hidden rounded-md border bg-white">
                <Skeleton v-if="loading && !html" class="h-[60vh] w-full" />
                <p
                    v-else-if="failed"
                    class="p-6 text-sm text-destructive"
                >
                    The preview could not be loaded. Please try again.
                </p>
                <iframe
                    v-else
                    :srcdoc="html"
                    sandbox=""
                    title="Email preview"
                    class="h-[60vh] w-full"
                    :class="{ 'opacity-60': loading }"
                />
            </div>
        </DialogContent>
    </Dialog>
</template>
