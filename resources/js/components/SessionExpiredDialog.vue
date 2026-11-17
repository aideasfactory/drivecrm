<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    isSessionExpired,
    logInAgain,
    SESSION_EXPIRED_MESSAGE,
} from '@/lib/sessionExpiry';
</script>

<template>
    <Dialog :open="isSessionExpired">
        <DialogContent
            :show-close-button="false"
            @escape-key-down.prevent
            @pointer-down-outside.prevent
            @interact-outside.prevent
        >
            <DialogHeader>
                <DialogTitle>Session expired</DialogTitle>
                <DialogDescription>
                    {{ SESSION_EXPIRED_MESSAGE }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button @click="logInAgain">Log in</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
