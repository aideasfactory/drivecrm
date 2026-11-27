<script setup lang="ts">
import { computed, ref, watch, type ComponentPublicInstance } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip'
import { ChevronRight, Users } from 'lucide-vue-next'
import EmailTokenInput from '@/components/EmailTemplates/EmailTokenInput.vue'
import {
    isActionButtonBlock,
    isBlockVisible,
    parseEmailBlocks,
    serializeEmailBlocks,
    type EmailBlock,
} from '@/lib/emailBlocks'

/**
 * The owner's view of an email: only the paragraphs the chosen learner gets,
 * in order, with wording editable and placeholders shown as chips. Who sees
 * which paragraph is fixed here; it is set up in the advanced editor.
 */

interface Option {
    key: string
    label: string
}

interface Props {
    modelValue: string
    conditions: Option[]
    placeholders: Option[]
    /** Cases that apply to the learner being shown. */
    reader: string[]
    actionText: string | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'focus-input', input: InstanceType<typeof EmailTokenInput>): void
}>()

const blocks = ref<EmailBlock[]>([])
let lastEmitted: string | null = null
let skipNextEmit = false

watch(
    () => props.modelValue,
    (body) => {
        if (body === lastEmitted) {
            return
        }

        skipNextEmit = true
        blocks.value = parseEmailBlocks(body) ?? []
        lastEmitted = body
    },
    { immediate: true },
)

watch(
    blocks,
    (value) => {
        if (skipNextEmit) {
            skipNextEmit = false

            return
        }

        const body = serializeEmailBlocks(value.filter((block) => block.text.trim() !== ''))
        lastEmitted = body
        emit('update:modelValue', body)
    },
    { deep: true },
)

const conditionKeys = computed(() => props.conditions.map((condition) => condition.key))

const labels = computed<Record<string, string>>(() =>
    Object.fromEntries(
        [...props.placeholders, ...props.conditions].map((option) => [option.key, option.label]),
    ),
)

const visibleBlocks = computed(() =>
    blocks.value.filter((block) => isBlockVisible(block, props.reader, conditionKeys.value)),
)

const otherBlocks = computed(() =>
    blocks.value.filter((block) => !isBlockVisible(block, props.reader, conditionKeys.value)),
)

const ruleText = (block: EmailBlock): string[] =>
    block.rules.map(
        (rule) =>
            `${rule.negate ? 'Not when' : 'Only when'}: ${labels.value[rule.key] ?? rule.key}`,
    )

const inputs: Record<string, InstanceType<typeof EmailTokenInput> | null> = {}

const setInput = (id: string, element: Element | ComponentPublicInstance | null) => {
    inputs[id] = element as InstanceType<typeof EmailTokenInput> | null
}

const focusInput = (id: string) => {
    const input = inputs[id]

    if (input) {
        emit('focus-input', input)
    }
}

const showOthers = ref(false)
</script>

<template>
    <TooltipProvider>
        <div class="space-y-3">
            <div class="space-y-3 rounded-md border bg-card p-4">
                <template v-for="block in visibleBlocks" :key="block.id">
                    <div v-if="isActionButtonBlock(block)" class="py-1">
                        <Button type="button" size="sm" disabled class="pointer-events-none">
                            {{ actionText || 'Button' }}
                        </Button>
                    </div>
                    <div v-else class="space-y-1">
                        <EmailTokenInput
                            :ref="(element) => setInput(block.id, element)"
                            v-model="block.text"
                            :labels="labels"
                            placeholder="Write this paragraph…"
                            @focused="focusInput(block.id)"
                        />
                        <Tooltip v-if="block.rules.length">
                            <TooltipTrigger as-child>
                                <Badge variant="outline" class="cursor-help gap-1 font-normal">
                                    <Users class="h-3 w-3" />
                                    Only some learners get this
                                </Badge>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs">
                                <p v-for="line in ruleText(block)" :key="line">{{ line }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </template>
                <p v-if="visibleBlocks.length === 0" class="text-sm text-muted-foreground">
                    This learner gets no body text in this email.
                </p>
            </div>

            <Collapsible v-if="otherBlocks.length" v-model:open="showOthers">
                <CollapsibleTrigger as-child>
                    <Button type="button" variant="ghost" size="sm" class="cursor-pointer px-2 text-muted-foreground">
                        <ChevronRight class="mr-1 h-4 w-4 transition-transform" :class="{ 'rotate-90': showOthers }" />
                        {{ otherBlocks.length }}
                        {{ otherBlocks.length === 1 ? 'paragraph' : 'paragraphs' }} other learners get
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent class="space-y-3 pt-2">
                    <p class="text-xs text-muted-foreground">
                        To edit these, change who you're looking at above.
                    </p>
                    <div v-for="block in otherBlocks" :key="block.id" class="space-y-1">
                        <EmailTokenInput :model-value="block.text" :labels="labels" readonly />
                        <p class="text-xs text-muted-foreground">{{ ruleText(block).join(' · ') }}</p>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>
    </TooltipProvider>
</template>
