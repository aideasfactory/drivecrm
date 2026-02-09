<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    FileText,
    GraduationCap,
    Grid3x3,
    LayoutGrid,
    Settings,
    Users,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useRole } from '@/composables/useRole';
import { dashboard } from '@/routes';
import { index as appsIndex } from '@/routes/apps';
import { index as instructorsIndex } from '@/routes/instructors';
import { edit as profileEdit } from '@/routes/profile';
import { index as pupilsIndex } from '@/routes/pupils';
import { index as reportsIndex } from '@/routes/reports';
import { index as resourcesIndex } from '@/routes/resources';
import { index as teamsIndex } from '@/routes/teams';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const { canSeeNavItem } = useRole();

// All navigation items with optional role restrictions
const allNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Instructors',
        href: instructorsIndex(),
        icon: GraduationCap,
        roles: ['owner'], // Only visible to owners
    },
    {
        title: 'Pupils',
        href: pupilsIndex(),
        icon: Users,
    },
    {
        title: 'Teams',
        href: teamsIndex(),
        icon: UsersRound,
    },
    {
        title: 'Reports',
        href: reportsIndex(),
        icon: FileText,
    },
    {
        title: 'Resources',
        href: resourcesIndex(),
        icon: BookOpen,
    },
    {
        title: 'Settings',
        href: profileEdit(),
        icon: Settings,
    },
    {
        title: 'Apps',
        href: appsIndex(),
        icon: Grid3x3,
    },
];

// Filter nav items based on user role
const mainNavItems = computed(() =>
    allNavItems.filter((item) => canSeeNavItem(item.roles)),
);

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
