<script setup lang="ts">
import EmergencyContactManager from '@/components/Shared/EmergencyContactManager.vue'

defineProps<{
    studentId: number
}>()
</script>

<template>
    <EmergencyContactManager
        :entity-id="studentId"
        entity-type="student"
    />
</template>
