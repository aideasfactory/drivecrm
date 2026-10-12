<script setup lang="ts">
import { ref, reactive } from 'vue'
import axios from 'axios'
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
import { toast } from '@/components/ui/sonner'
import { UserPlus, Loader2, Send, RefreshCw, Copy, ShieldCheck, Shield } from 'lucide-vue-next'
import { store as storeOwner } from '@/routes/owners'
import type { OwnerAccessType } from '@/types'

export interface CreatedOwner {
    id: number
    name: string
    email: string
    owner_access: OwnerAccessType
    owner_access_label: string
    created_at: string | null
}

interface Props {
    open: boolean
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'created', owner: CreatedOwner): void
}

defineProps<Props>()
const emit = defineEmits<Emits>()

const accessOptions: { value: OwnerAccessType; label: string; icon: typeof ShieldCheck }[] = [
    { value: 'all', label: 'All', icon: ShieldCheck },
    { value: 'restricted', label: 'Restricted', icon: Shield },
]

/**
 * Readable temp password — skips look-alike characters (0/O, 1/l/I).
 */
const generatePassword = (): string => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'
    const values = crypto.getRandomValues(new Uint32Array(12))
    return Array.from(values, (value) => chars[value % chars.length]).join('')
}

const saving = ref(false)
const form = reactive({
    name: '',
    email: '',
    password: generatePassword(),
    owner_access: 'restricted' as OwnerAccessType,
})
const errors = reactive<Record<string, string>>({})

const resetForm = () => {
    form.name = ''
    form.email = ''
    form.password = generatePassword()
    form.owner_access = 'restricted'
    Object.keys(errors).forEach((key) => delete errors[key])
}

const copyPassword = async () => {
    try {
        await navigator.clipboard.writeText(form.password)
        toast.success('Password copied')
    } catch {
        toast.error('Could not copy password')
    }
}

const handleSave = async () => {
    saving.value = true
    Object.keys(errors).forEach((key) => delete errors[key])

    try {
        const response = await axios.post(storeOwner.url(), { ...form })
        const owner: CreatedOwner = response.data.owner
        toast.success(`${owner.name} added — login details emailed to ${owner.email}`)
        emit('created', owner)
        emit('update:open', false)
        resetForm()
    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {}
            Object.entries(validationErrors).forEach(([key, msgs]) => {
                errors[key] = (msgs as string[])[0]
            })
        } else {
            toast.error(error.response?.data?.message || 'Failed to add admin')
        }
    } finally {
        saving.value = false
    }
}

const handleOpenChange = (value: boolean) => {
    if (!saving.value) {
        emit('update:open', value)
        if (!value) {
            resetForm()
        }
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="handleOpenChange">
        <SheetContent side="right" class="overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <UserPlus class="h-5 w-5" />
                    Add Admin
                </SheetTitle>
                <SheetDescription>
                    Create an admin account with a temporary password. Their login
                    details are emailed to them, and they should change the password
                    after signing in.
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="handleSave" class="mt-6 space-y-6 px-6 py-4">
                <div class="space-y-2">
                    <Label for="admin-name">Name</Label>
                    <Input
                        id="admin-name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g. Jane Smith"
                        autocomplete="off"
                    />
                    <p v-if="errors.name" class="text-sm text-destructive">
                        {{ errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="admin-email">Email</Label>
                    <Input
                        id="admin-email"
                        v-model="form.email"
                        type="email"
                        placeholder="jane@example.com"
                        autocomplete="off"
                    />
                    <p v-if="errors.email" class="text-sm text-destructive">
                        {{ errors.email }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="admin-password">Temporary password</Label>
                    <div class="flex gap-2">
                        <Input
                            id="admin-password"
                            v-model="form.password"
                            type="text"
                            autocomplete="new-password"
                            class="font-mono"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            title="Generate new password"
                            class="cursor-pointer"
                            @click="form.password = generatePassword()"
                        >
                            <RefreshCw class="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            title="Copy password"
                            class="cursor-pointer"
                            @click="copyPassword"
                        >
                            <Copy class="h-4 w-4" />
                        </Button>
                    </div>
                    <p v-if="errors.password" class="text-sm text-destructive">
                        {{ errors.password }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Minimum 8 characters. This is included in the welcome email.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Access level</Label>
                    <div class="flex gap-2">
                        <Button
                            v-for="option in accessOptions"
                            :key="option.value"
                            type="button"
                            size="sm"
                            :variant="form.owner_access === option.value ? 'default' : 'outline'"
                            class="min-w-[110px] cursor-pointer"
                            @click="form.owner_access = option.value"
                        >
                            <component :is="option.icon" class="mr-2 h-4 w-4" />
                            {{ option.label }}
                        </Button>
                    </div>
                    <p v-if="errors.owner_access" class="text-sm text-destructive">
                        {{ errors.owner_access }}
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="saving"
                        class="cursor-pointer"
                        @click="handleOpenChange(false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="saving || !form.name || !form.email || !form.password"
                        class="min-w-[160px] cursor-pointer"
                    >
                        <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                        <Send v-else class="mr-2 h-4 w-4" />
                        {{ saving ? 'Adding...' : 'Add & email login' }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
