/**
 * User role enum matching backend UserRole enum
 */
export enum UserRole {
    OWNER = 'owner',
    INSTRUCTOR = 'instructor',
    STUDENT = 'student',
}

/**
 * Type alias for role string values
 */
export type UserRoleType = 'owner' | 'instructor' | 'student';

/**
 * Owner admin-area access level matching backend OwnerAccess enum
 */
export type OwnerAccessType = 'all' | 'restricted';
