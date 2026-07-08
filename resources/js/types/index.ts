export * from './auth';
export * from './navigation';
export * from './roles';
export * from './ui';

import type { Auth } from './auth';

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    hmrc: {
        show_mtd_button: boolean;
    };
    [key: string]: unknown;
};
