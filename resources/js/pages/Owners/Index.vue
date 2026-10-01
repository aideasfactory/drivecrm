<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { toast } from '@/components/ui/sonner'
import { Search, ShieldCheck, Shield, Loader2, UserCog } from 'lucide-vue-next'
import { useRole } from '@/composables/useRole'
import { update as updateOwnerAccess } from '@/routes/owners/access'
import type { OwnerAccessType } from '@/types'
import axios from 'axios'

interface OwnerItem {
    id: number
    name: string
    email: string
    owner_access: OwnerAccessType
    owner_access_label: string
    created_at: string | null
}

interface Props {
    owners: OwnerItem[]
}

const props = defineProps<Props>()

const { user } = useRole()

const owners = ref<OwnerItem[]>([...props.owners])
const searchQuery = ref('')
const savingId = ref<number | null>(null)

const accessOptions: { value: OwnerAccessType; label: string; icon: typeof ShieldCheck }[] = [
    { value: 'all', label: 'All', icon: ShieldCheck },
    { value: 'restricted', label: 'Restricted', icon: Shield },
]

const filteredOwners = computed(() => {
    if (!searchQuery.value) {
        return owners.value
    }

    const query = searchQuery.value.toLowerCase()
    return owners.value.filter(
        (owner) =>
            owner.name.toLowerCase().includes(query) ||
            owner.email.toLowerCase().includes(query),
    )
})

const isCurrentUser = (owner: OwnerItem): boolean => owner.id === user.value?.id

const setAccess = async (owner: OwnerItem, access: OwnerAccessType) => {
    if (owner.owner_access === access || isCurrentUser(owner)) return

    savingId.value = owner.id

    try {
        const response = await axios.patch(updateOwnerAccess.url(owner.id), {
            owner_access: access,
        })
        const updated: OwnerItem = response.data.owner
        owners.value = owners.value.map((item) => (item.id === updated.id ? updated : item))
        toast.success(`${updated.name} now has ${updated.owner_access_label.toLowerCase()}`)
    } catch (error: any) {
        const message =
            error.response?.data?.errors?.owner_access?.[0] ||
            error.response?.data?.message ||
            'Failed to update owner access'
        toast.error(message)
    } finally {
        savingId.value = null
    }
}

const breadcrumbs = [{ title: 'Owner Access' }]
</script>

<template>
    <Head title="Owner Access" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold">Owner Access</h2>
                <p class="text-muted-foreground">
                    "All" owners see the entire admin area. "Restricted" owners only see
                    Instructors, Students, Transfer Student, Support Messages and Enquiries.
                </p>
            </div>

            <!-- Search -->
            <div class="relative max-w-md">
                <Search
                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search owners..."
                    class="pl-9"
                />
            </div>

            <!-- Owners Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Access</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="text-right">Change access</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="owner in filteredOwners" :key="owner.id">
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <UserCog class="h-4 w-4 text-muted-foreground" />
                                        <span class="font-semibold">{{ owner.name }}</span>
                                        <Badge v-if="isCurrentUser(owner)" variant="outline">
                                            You
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-muted-foreground">{{ owner.email }}</span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="owner.owner_access === 'all' ? 'default' : 'secondary'"
                                    >
                                        {{ owner.owner_access_label }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span class="text-muted-foreground">{{ owner.created_at }}</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Loader2
                                            v-if="savingId === owner.id"
                                            class="h-4 w-4 animate-spin text-muted-foreground"
                                        />
                                        <Button
                                            v-for="option in accessOptions"
                                            :key="option.value"
                                            size="sm"
                                            :variant="owner.owner_access === option.value ? 'default' : 'outline'"
                                            :disabled="isCurrentUser(owner) || savingId !== null"
                                            :title="isCurrentUser(owner) ? 'You cannot change your own access' : undefined"
                                            class="min-w-[110px] cursor-pointer"
                                            @click="setAccess(owner, option.value)"
                                        >
                                            <component :is="option.icon" class="mr-2 h-4 w-4" />
                                            {{ option.label }}
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredOwners.length === 0">
                                <TableCell colspan="5" class="text-center">
                                    <div class="py-8 text-muted-foreground">
                                        No owners found
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
