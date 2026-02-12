<script setup lang="ts">
import { isVNode } from 'vue'
import { useToast } from './use-toast'
import Toast from './Toast.vue'
import ToastClose from './ToastClose.vue'
import ToastDescription from './ToastDescription.vue'
import ToastProvider from './ToastProvider.vue'
import ToastTitle from './ToastTitle.vue'
import ToastViewport from './ToastViewport.vue'

const { toasts } = useToast()
</script>

<template>
    <ToastProvider>
        <Toast
            v-for="t in toasts"
            :key="t.id"
            :variant="t.variant"
            :open="t.open"
            :on-open-change="t.onOpenChange"
        >
            <div class="grid gap-1">
                <ToastTitle v-if="t.title">
                    {{ t.title }}
                </ToastTitle>
                <template v-if="t.description">
                    <ToastDescription v-if="isVNode(t.description)">
                        <component :is="t.description" />
                    </ToastDescription>
                    <ToastDescription v-else>
                        {{ t.description }}
                    </ToastDescription>
                </template>
            </div>
            <component :is="t.action" v-if="t.action" />
            <ToastClose />
        </Toast>
        <ToastViewport />
    </ToastProvider>
</template>
