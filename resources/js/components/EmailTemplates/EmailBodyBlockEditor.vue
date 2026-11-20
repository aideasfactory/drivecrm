<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
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
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    ArrowDown,
    ArrowUp,
    EyeOff,
    MoreHorizontal,
    MousePointerClick,
    Plus,
    Split,
    Trash2,
    Users,
    Wand2,
} from 'lucide-vue-next'
import {
    PARAGRAPH_SEPARATOR,
    inlineConditionKeys,
    isActionButtonBlock,
    isBlockVisible,
    newBlockId,
    parseEmailBlocks,
    serializeEmailBlocks,
    type EmailBlock,
    type EmailBlockRule,
} from '@/lib/emailBlocks'

interface Option {
    key: string
    label: string
}

interface Props {
    modelValue: string
    conditions: Option[]
    placeholders: Option[]
    /** Conditions ticked under "Preview as". */
    preview: string[]
    actionText: string | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
}>()

const blocks = ref<EmailBlock[]>([])
let lastEmitted: string | null = null
/** Skip emitting for a parse of the incoming body, so opening an email never rewrites it. */
let skipNextEmit = false

const focus = ref<{ id: string | null; start: number | null; end: number | null }>({
    id: null,
    start: null,
    end: null,
})

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

const ruleLabel = (key: string): string => {
    const condition = props.conditions.find((option) => option.key === key)

    if (condition) {
        return condition.label
    }

    const placeholder = props.placeholders.find((option) => option.key === key)

    return placeholder ? `${placeholder.label} has a value` : key
}

/** Conditions offered in "Who sees this": the email's own, plus any already in use. */
const ruleOptions = (block: EmailBlock): Option[] => {
    const extra = block.rules
        .filter((rule) => !conditionKeys.value.includes(rule.key))
        .map((rule) => ({ key: rule.key, label: ruleLabel(rule.key) }))

    return [...props.conditions, ...extra]
}

const ruleState = (block: EmailBlock, key: string): 'any' | 'yes' | 'no' => {
    const rule = block.rules.find((candidate) => candidate.key === key)

    if (!rule) {
        return 'any'
    }

    return rule.negate ? 'no' : 'yes'
}

const setRule = (block: EmailBlock, key: string, state: 'any' | 'yes' | 'no') => {
    const index = block.rules.findIndex((rule) => rule.key === key)

    if (state === 'any') {
        if (index !== -1) {
            block.rules.splice(index, 1)
        }

        return
    }

    const rule: EmailBlockRule = { key, negate: state === 'no' }

    if (index === -1) {
        block.rules.push(rule)
    } else {
        block.rules.splice(index, 1, rule)
    }
}

const isVisible = (block: EmailBlock): boolean =>
    isBlockVisible(block, props.preview, conditionKeys.value)

const inlineLabels = (block: EmailBlock): string[] =>
    inlineConditionKeys(block.text).map(ruleLabel)

const newBlock = (rules: EmailBlockRule[]): EmailBlock => ({
    id: newBlockId(),
    sep: PARAGRAPH_SEPARATOR,
    text: '',
    rules: rules.map((rule) => ({ ...rule })),
})

/** New paragraphs are shown in the case being previewed. */
const previewRules = computed<EmailBlockRule[]>(() =>
    props.preview.map((key) => ({ key, negate: false })),
)

const previewSummary = computed(() =>
    props.preview.length ? props.preview.map(ruleLabel).join(' and ') : 'everyone',
)

const addBlock = (afterIndex: number | null = null) => {
    const block = newBlock(previewRules.value)
    const index = afterIndex === null ? blocks.value.length : afterIndex + 1

    if (index === 0) {
        block.sep = ''

        if (blocks.value[0]) {
            blocks.value[0].sep = PARAGRAPH_SEPARATOR
        }
    }

    blocks.value.splice(index, 0, block)
    focus.value = { id: block.id, start: 0, end: 0 }
}

const removeBlock = (index: number) => {
    const [removed] = blocks.value.splice(index, 1)

    if (index === 0 && blocks.value[0]) {
        blocks.value[0].sep = removed.sep
    }
}

/** Swap two neighbours, keeping each position's separator. */
const moveBlock = (index: number, direction: -1 | 1) => {
    const other = index + direction

    if (other < 0 || other >= blocks.value.length) {
        return
    }

    const first = blocks.value[index]
    const second = blocks.value[other]
    const firstSep = first.sep

    first.sep = second.sep
    second.sep = firstSep
    blocks.value.splice(index, 1, second)
    blocks.value.splice(other, 1, first)
}

/**
 * "Different wording when X": a copy shown only when X goes first, and this
 * paragraph is then shown only when not X.
 */
const splitBlock = (index: number, key: string) => {
    const block = blocks.value[index]
    const copy: EmailBlock = {
        id: newBlockId(),
        sep: block.sep,
        text: block.text,
        rules: [...block.rules.map((rule) => ({ ...rule })), { key, negate: false }],
    }

    block.rules.push({ key, negate: true })
    blocks.value.splice(index, 0, copy)
}

const rememberCursor = (block: EmailBlock, event: Event) => {
    const target = event.target as HTMLTextAreaElement
    focus.value = { id: block.id, start: target.selectionStart, end: target.selectionEnd }
}

const insertPlaceholder = (key: string) => {
    const block =
        blocks.value.find((candidate) => candidate.id === focus.value.id) ??
        blocks.value.filter((candidate) => !isActionButtonBlock(candidate)).at(-1)

    if (!block) {
        return
    }

    const token = `{{${key}}}`
    const start = focus.value.id === block.id ? (focus.value.start ?? block.text.length) : block.text.length
    const end = focus.value.id === block.id ? (focus.value.end ?? start) : start

    block.text = `${block.text.slice(0, start)}${token}${block.text.slice(end)}`
    focus.value = { id: block.id, start: start + token.length, end: start + token.length }
}

const textRows = (text: string): number => Math.min(12, Math.max(2, text.split('\n').length + 1))
</script>

<template>
    <div class="space-y-3">
        <p v-if="blocks.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
            This email has no paragraphs yet.
        </p>

        <div
            v-for="(block, index) in blocks"
            :key="block.id"
            class="space-y-2 rounded-md border p-3"
            :class="isVisible(block) ? 'bg-card' : 'border-dashed bg-muted/40'"
        >
            <div class="flex items-start justify-between gap-2">
                <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                    <Badge v-if="block.rules.length === 0" variant="secondary">Everyone</Badge>
                    <template v-for="rule in block.rules" :key="`${rule.key}-${rule.negate}`">
                        <Badge :variant="rule.negate ? 'outline' : 'default'" class="max-w-full whitespace-normal text-left">
                            {{ rule.negate ? 'Not when' : 'Only when' }}: {{ ruleLabel(rule.key) }}
                        </Badge>
                    </template>
                    <span
                        v-if="!isVisible(block)"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <EyeOff class="h-3 w-3" />
                        Not in this preview
                    </span>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Popover>
                        <PopoverTrigger as-child>
                            <Button type="button" variant="ghost" size="sm" class="cursor-pointer">
                                <Users class="mr-1 h-3.5 w-3.5" />
                                Who sees this
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent align="end" class="w-80 space-y-3">
                            <div>
                                <p class="text-sm font-medium">Who sees this paragraph</p>
                                <p class="text-xs text-muted-foreground">
                                    Leave everything on "Any" to show it to everyone who gets this email.
                                </p>
                            </div>
                            <p v-if="ruleOptions(block).length === 0" class="text-xs text-muted-foreground">
                                This email has no cases to choose from, so every paragraph goes to everyone.
                            </p>
                            <div
                                v-for="option in ruleOptions(block)"
                                :key="option.key"
                                class="space-y-1.5 border-t pt-2 first:border-t-0 first:pt-0"
                            >
                                <p class="text-xs leading-snug">{{ option.label }}</p>
                                <div class="flex gap-1">
                                    <Button
                                        v-for="state in (['any', 'yes', 'no'] as const)"
                                        :key="state"
                                        type="button"
                                        size="sm"
                                        :variant="ruleState(block, option.key) === state ? 'default' : 'outline'"
                                        class="h-7 flex-1 cursor-pointer text-xs"
                                        @click="setRule(block, option.key, state)"
                                    >
                                        {{ state === 'any' ? 'Any' : state === 'yes' ? 'Only when' : 'Not when' }}
                                    </Button>
                                </div>
                            </div>
                        </PopoverContent>
                    </Popover>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="ghost" size="sm" class="cursor-pointer px-2">
                                <MoreHorizontal class="h-4 w-4" />
                                <span class="sr-only">Paragraph options</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-64">
                            <DropdownMenuSub v-if="conditions.length && !isActionButtonBlock(block)">
                                <DropdownMenuSubTrigger class="cursor-pointer">
                                    <Split class="mr-2 h-4 w-4" />
                                    Different wording when…
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent class="w-72">
                                    <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">
                                        Adds a copy for this case to reword
                                    </DropdownMenuLabel>
                                    <DropdownMenuItem
                                        v-for="condition in conditions"
                                        :key="condition.key"
                                        :disabled="block.rules.some((rule) => rule.key === condition.key)"
                                        class="cursor-pointer whitespace-normal"
                                        @click="splitBlock(index, condition.key)"
                                    >
                                        {{ condition.label }}
                                    </DropdownMenuItem>
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                            <DropdownMenuItem class="cursor-pointer" @click="addBlock(index)">
                                <Plus class="mr-2 h-4 w-4" />
                                Add paragraph below
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                :disabled="index === 0"
                                class="cursor-pointer"
                                @click="moveBlock(index, -1)"
                            >
                                <ArrowUp class="mr-2 h-4 w-4" />
                                Move up
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                :disabled="index === blocks.length - 1"
                                class="cursor-pointer"
                                @click="moveBlock(index, 1)"
                            >
                                <ArrowDown class="mr-2 h-4 w-4" />
                                Move down
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="cursor-pointer text-destructive" @click="removeBlock(index)">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete paragraph
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div
                v-if="isActionButtonBlock(block)"
                class="flex items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground"
            >
                <MousePointerClick class="h-4 w-4" />
                Button: <span class="font-medium text-foreground">{{ actionText || 'No button label set' }}</span>
            </div>
            <template v-else>
                <Textarea
                    v-model="block.text"
                    :rows="textRows(block.text)"
                    class="text-sm"
                    placeholder="Write this paragraph…"
                    @focus="rememberCursor(block, $event)"
                    @blur="rememberCursor(block, $event)"
                    @keyup="rememberCursor(block, $event)"
                    @click="rememberCursor(block, $event)"
                />
                <p
                    v-if="inlineLabels(block).length"
                    class="flex items-start gap-1.5 text-xs text-muted-foreground"
                >
                    <Wand2 class="mt-0.5 h-3 w-3 shrink-0" />
                    <span>
                        Contains wording that changes automatically ({{ inlineLabels(block).join('; ') }}).
                        Keep the <code v-text="'{{#if …}}'" /> … <code v-text="'{{/if}}'" /> parts as they are.
                    </span>
                </p>
            </template>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button type="button" variant="outline" size="sm" class="cursor-pointer" @click="addBlock()">
                <Plus class="mr-1 h-3.5 w-3.5" />
                Add paragraph
            </Button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer">
                        <Wand2 class="mr-1 h-3.5 w-3.5" />
                        Insert placeholder
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="max-h-80 w-80 overflow-y-auto">
                    <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">
                        Goes where your cursor was in the last paragraph you edited
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="placeholder in placeholders"
                        :key="placeholder.key"
                        class="cursor-pointer flex-col items-start gap-0.5"
                        @click="insertPlaceholder(placeholder.key)"
                    >
                        <code class="text-xs font-medium" v-text="`{{${placeholder.key}}}`" />
                        <span class="text-xs text-muted-foreground">{{ placeholder.label }}</span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <span class="text-xs text-muted-foreground">
                New paragraphs are shown to {{ previewSummary }}.
            </span>
        </div>
    </div>
</template>
