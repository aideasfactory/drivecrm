<script setup lang="ts">
import EmergencyContactManager from '@/components/Shared/EmergencyContactManager.vue'
import type { InstructorDetail } from '@/types/instructor'

const props = defineProps<{
    instructor: InstructorDetail
}>()
</script>

<template>
    <EmergencyContactManager
        :entity-id="instructor.id"
        entity-type="instructor"
    />
</template>
