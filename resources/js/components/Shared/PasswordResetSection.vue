<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Loader2, KeyRound, Save } from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'

interface Props {
    /** The URL to PUT the password reset to (e.g. /instructors/1/password) */
    resetUrl: string
}

const props = defineProps<Props>()

const password = ref('')
const passwordConfirmation = ref('')
const isSubmitting = ref(false)
const errors = ref<Record<string, string>>({})

const handleSubmit = async () => {
    isSubmitting.value = true
    errors.value = {}

    try {
        await axios.put(props.resetUrl, {
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        })

        password.value = ''
        passwordConfirmation.value = ''
        toast.success('Password has been reset successfully.')
    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors
            for (const key in validationErrors) {
                errors.value[key] = validationErrors[key][0]
            }
        } else {
            const message = error.response?.data?.message || 'Failed to reset password.'
            toast.error(message)
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div>
        <div class="flex items-center gap-2 mb-4">
            <KeyRound class="h-5 w-5 text-muted-foreground" />
            <h3 class="font-semibold">Reset Password</h3>
        </div>
        <p class="text-sm text-muted-foreground mb-4">
            Set a new password for this account. The user will need to use this new password to log in.
        </p>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="space-y-2">
                <Label for="new-password">New Password</Label>
                <Input
                    id="new-password"
                    v-model="password"
                    type="password"
                    placeholder="Enter new password"
                    :disabled="isSubmitting"
                />
                <p v-if="errors.password" class="text-sm text-destructive">
                    {{ errors.password }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="confirm-password">Confirm Password</Label>
                <Input
                    id="confirm-password"
                    v-model="passwordConfirmation"
                    type="password"
                    placeholder="Confirm new password"
                    :disabled="isSubmitting"
                />
            </div>

            <Button
                type="submit"
                :disabled="isSubmitting || !password || !passwordConfirmation"
                class="min-w-[140px]"
            >
                <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                <Save v-else class="mr-2 h-4 w-4" />
                Reset Password
            </Button>
        </form>
    </div>
</template>
