<script setup lang="ts">
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { Input } from '@/components/ui/input'
import { Loader2, Search, X } from 'lucide-vue-next'

export interface TransferSearchOption {
    id: number
    name: string
    email: string | null
    phone: string | null
    [key: string]: unknown
}

interface Props {
    endpoint: string
    resultsKey: string
    placeholder: string
    inputId: string
    modelValue: TransferSearchOption | null
    excludeId?: number | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
    'update:modelValue': [value: TransferSearchOption | null]
}>()

const MIN_QUERY_LENGTH = 2
const DEBOUNCE_MS = 300

const container = ref<HTMLElement | null>(null)
const query = ref('')
const results = ref<TransferSearchOption[]>([])
const open = ref(false)
const loading = ref(false)
const searched = ref(false)
const failed = ref(false)
const highlightedIndex = ref(-1)

let debounceTimer: ReturnType<typeof setTimeout> | null = null
let abortController: AbortController | null = null

const visibleResults = computed(() =>
    props.excludeId == null
        ? results.value
        : results.value.filter((option) => option.id !== props.excludeId),
)

const search = async (term: string) => {
    abortController?.abort()
    abortController = new AbortController()

    loading.value = true
    failed.value = false

    try {
        const response = await axios.get(props.endpoint, {
            params: { q: term },
            signal: abortController.signal,
        })
        results.value = response.data[props.resultsKey] ?? []
        searched.value = true
        highlightedIndex.value = -1
        open.value = true
        loading.value = false
    } catch (error) {
        if (axios.isCancel(error)) return
        results.value = []
        searched.value = true
        failed.value = true
        open.value = true
        loading.value = false
    }
}

watch(query, (term) => {
    if (debounceTimer) clearTimeout(debounceTimer)

    const trimmed = term.trim()

    if (trimmed.length < MIN_QUERY_LENGTH) {
        abortController?.abort()
        results.value = []
        searched.value = false
        failed.value = false
        loading.value = false
        open.value = trimmed.length > 0
        return
    }

    debounceTimer = setTimeout(() => search(trimmed), DEBOUNCE_MS)
})

const select = (option: TransferSearchOption) => {
    emit('update:modelValue', option)
    query.value = ''
    results.value = []
    searched.value = false
    open.value = false
}

const clear = () => {
    emit('update:modelValue', null)
}

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        open.value = false
        return
    }

    if (!open.value || visibleResults.value.length === 0) return

    if (event.key === 'ArrowDown') {
        event.preventDefault()
        highlightedIndex.value =
            (highlightedIndex.value + 1) % visibleResults.value.length
    } else if (event.key === 'ArrowUp') {
        event.preventDefault()
        highlightedIndex.value =
            highlightedIndex.value <= 0
                ? visibleResults.value.length - 1
                : highlightedIndex.value - 1
    } else if (event.key === 'Enter') {
        event.preventDefault()
        const option = visibleResults.value[highlightedIndex.value] ?? visibleResults.value[0]
        if (option) select(option)
    }
}

const handleFocus = () => {
    if (query.value.trim().length > 0) {
        open.value = true
    }
}

const handleClickOutside = (event: MouseEvent) => {
    if (container.value && !container.value.contains(event.target as Node)) {
        open.value = false
    }
}

const contactLine = (option: TransferSearchOption) =>
    [option.email, option.phone].filter(Boolean).join(' · ')

onMounted(() => document.addEventListener('mousedown', handleClickOutside))

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside)
    if (debounceTimer) clearTimeout(debounceTimer)
    abortController?.abort()
})
</script>

<template>
    <div ref="container" class="relative">
        <div
            v-if="props.modelValue"
            class="flex items-center justify-between gap-3 rounded-md border border-input px-3 py-2"
        >
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">
                    {{ props.modelValue.name }}
                </p>
                <p
                    v-if="contactLine(props.modelValue)"
                    class="truncate text-xs text-muted-foreground"
                >
                    {{ contactLine(props.modelValue) }}
                </p>
            </div>
            <button
                type="button"
                @click="clear"
                class="cursor-pointer flex-shrink-0 rounded-md p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                aria-label="Clear selection"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <template v-else>
            <div class="relative">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    :id="props.inputId"
                    v-model="query"
                    type="text"
                    class="pl-9 pr-9"
                    :placeholder="props.placeholder"
                    autocomplete="off"
                    @keydown="handleKeydown"
                    @focus="handleFocus"
                />
                <Loader2
                    v-if="loading"
                    class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-foreground"
                />
            </div>

            <div
                v-if="open"
                class="absolute z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <p
                    v-if="query.trim().length < MIN_QUERY_LENGTH"
                    class="px-3 py-2 text-sm text-muted-foreground"
                >
                    Type at least {{ MIN_QUERY_LENGTH }} characters to search...
                </p>
                <p
                    v-else-if="failed"
                    class="px-3 py-2 text-sm text-destructive"
                >
                    Search failed. Please try again.
                </p>
                <p
                    v-else-if="searched && visibleResults.length === 0"
                    class="px-3 py-2 text-sm text-muted-foreground"
                >
                    No matches found.
                </p>
                <ul
                    v-else-if="visibleResults.length > 0"
                    class="max-h-64 overflow-y-auto py-1"
                >
                    <li
                        v-for="(option, index) in visibleResults"
                        :key="option.id"
                    >
                        <button
                            type="button"
                            class="w-full cursor-pointer px-3 py-2 text-left"
                            :class="{ 'bg-accent': index === highlightedIndex }"
                            @click="select(option)"
                            @mousemove="highlightedIndex = index"
                        >
                            <p class="truncate text-sm font-medium">
                                {{ option.name }}
                            </p>
                            <p
                                v-if="contactLine(option)"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ contactLine(option) }}
                            </p>
                        </button>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
