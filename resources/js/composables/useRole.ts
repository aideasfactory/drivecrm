import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AppPageProps } from '@/types';
import type { UserRoleType } from '@/types/roles';
import { UserRole } from '@/types/roles';

/**
 * Composable for accessing and checking user roles
 *
 * @example
 * ```vue
 * <script setup>
 * import { useRole } from '@/composables/useRole';
 *
 * const { role, isOwner, isInstructor, isStudent, hasRole } = useRole();
 * </script>
 *
 * <template>
 *   <div v-if="isOwner">Owner only content</div>
 *   <div v-if="hasRole(['owner', 'instructor'])">Owner or Instructor content</div>
 * </template>
 * ```
 */
export function useRole() {
    const page = usePage<AppPageProps>();

    /**
     * Current user object
     */
    const user = computed(() => page.props.auth?.user);

    /**
     * Current user's role
     */
    const role = computed<UserRoleType | undefined>(() => user.value?.role);

    /**
     * Check if current user is an owner
     */
    const isOwner = computed(() => role.value === UserRole.OWNER);

    /**
     * Check if current user is an instructor
     */
    const isInstructor = computed(() => role.value === UserRole.INSTRUCTOR);

    /**
     * Check if current user is a student
     */
    const isStudent = computed(() => role.value === UserRole.STUDENT);

    /**
     * Check if current user has one of the specified roles
     *
     * @param roles - Array of roles to check against
     * @returns true if user has any of the specified roles
     */
    const hasRole = (roles: UserRoleType[]): boolean => {
        if (!role.value) return false;
        return roles.includes(role.value);
    };

    /**
     * Check if a navigation item should be visible based on roles
     *
     * @param allowedRoles - Optional array of roles that can see the item. If undefined, visible to all.
     * @returns true if item should be visible
     */
    const canSeeNavItem = (allowedRoles?: UserRoleType[]): boolean => {
        if (!allowedRoles || allowedRoles.length === 0) return true;
        return hasRole(allowedRoles);
    };

    return {
        user,
        role,
        isOwner,
        isInstructor,
        isStudent,
        hasRole,
        canSeeNavItem,
    };
}
