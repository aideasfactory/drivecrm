<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import EmergencyContactManager from '@/components/Shared/EmergencyContactManager.vue'
import PickupPointsSection from '@/components/Instructors/Tabs/Student/Actions/PickupPointsSection.vue'
import StudentStatusSection from '@/components/Instructors/Tabs/Student/Actions/StudentStatusSection.vue'
import RemoveStudentSection from '@/components/Instructors/Tabs/Student/Actions/RemoveStudentSection.vue'
import StudentChecklistSection from '@/components/Instructors/Tabs/Student/Actions/StudentChecklistSection.vue'

interface Props {
    studentId: number
}

const props = defineProps<Props>()

const student = ref<{
    student_status: string
    inactive_reason: string | null
    instructor_id: number | null
} | null>(null)
const isLoadingStudent = ref(true)
const contactManagerKey = ref(0)

onMounted(async () => {
    const [studentResponse] = await Promise.allSettled([
        axios.get(`/students/${props.studentId}`),
        axios
            .post(`/students/${props.studentId}/contacts/auto-create`)
            .then((res) => {
                if (res.data.created) {
                    contactManagerKey.value++
                }
            }),
    ])

    if (studentResponse.status === 'fulfilled') {
        student.value = studentResponse.value.data.student
    }

    isLoadingStudent.value = false
})

const handleStatusUpdated = (
    status: string,
    reason: string | null,
) => {
    if (student.value) {
        student.value.student_status = status
        student.value.inactive_reason = reason
    }
}
</script>

<template>
    <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
        <!-- Emergency Contacts -->
        <Card>
            <CardContent class="p-6">
                <EmergencyContactManager
                    :key="contactManagerKey"
                    :entity-id="studentId"
                    entity-type="student"
                />
            </CardContent>
        </Card>

        <!-- Pickup Points -->
        <Card>
            <CardContent class="p-6">
                <PickupPointsSection :student-id="studentId" />
            </CardContent>
        </Card>

        <!-- Student Status -->
        <Card>
            <CardContent class="p-6">
                <div v-if="isLoadingStudent" class="space-y-4">
                    <Skeleton class="h-5 w-32" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-20 w-full" />
                    <Skeleton class="h-10 w-full" />
                </div>
                <StudentStatusSection
                    v-else-if="student"
                    :student-id="studentId"
                    :current-status="student.student_status"
                    :inactive-reason="student.inactive_reason"
                    @updated="handleStatusUpdated"
                />
            </CardContent>
        </Card>

        <!-- Student Checklist (full width) -->
        <Card class="md:col-span-2">
            <CardContent class="p-6">
                <StudentChecklistSection :student-id="studentId" />
            </CardContent>
        </Card>

        <!-- General Actions - Remove Student (full width) -->
        <Card class="md:col-span-2">
            <CardContent class="p-6">
                <div v-if="isLoadingStudent" class="space-y-4">
                    <Skeleton class="h-5 w-40" />
                    <Skeleton class="h-4 w-80" />
                    <Skeleton class="h-10 w-40" />
                </div>
                <RemoveStudentSection
                    v-else-if="student"
                    :student-id="studentId"
                    :has-instructor="student.instructor_id !== null"
                />
            </CardContent>
        </Card>
    </div>
</template>
