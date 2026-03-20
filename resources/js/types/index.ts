export * from './auth';
export * from './navigation';
export * from './roles';
export * from './ui';

import type { Auth } from './auth';

export type TeamSettings = {
    primary_color: string | null;
    default_slot_duration_minutes: number;
};

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    teamSettings: TeamSettings;
    [key: string]: unknown;
};
