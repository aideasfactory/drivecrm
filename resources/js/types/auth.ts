import type { OwnerAccessType, UserRoleType } from './roles';

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRoleType;
    owner_access?: OwnerAccessType;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    instructor_id: number | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
