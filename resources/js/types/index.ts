export * from './auth';
export * from './navigation';
export * from './roles';
export * from './ui';

import type { Auth } from './auth';

export type SchoolContact = {
    name: string;
    location: string;
    phone: string;
    email: string;
};

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    hmrcFlags: {
        show_mtd_button: boolean;
    };
    appLinks: {
        apple: string | null;
        android: string | null;
    };
    schoolContact: SchoolContact;
    [key: string]: unknown;
};
