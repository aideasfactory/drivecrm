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
