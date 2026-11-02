import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    roles: string[];
    permissions: string[];
    homeUrl: string;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success?: string };
    datatable: { perPage: number; pageSizeOptions: number[] };
    activeModules: string[];
    moduleDisplayLocations: Record<string, Array<'sidebar' | 'tabs' | 'dropdown'>>;
    notifications: {
        unreadCount: number;
        items: Array<{ id: string; title: string; message: string; ticketNumber?: string; read: boolean; createdAt: string }>;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    status: boolean;
    [key: string]: unknown; // This allows for additional properties...
}
