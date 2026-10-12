import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';
import type { UserRoleType } from './roles';

export type BreadcrumbItem = {
    title: string;
    href?: string;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    roles?: UserRoleType[];
    /** Visible to owners with restricted access (hidden from them otherwise). */
    restrictedOwnerVisible?: boolean;
};
