export interface Auth {
    user: User;
    identity?: {
        name?: string;
        subtitle?: string | null;
    } | null;
    announcements?: {
        unread_count?: number;
    };
    coach_notifications?: {
        total?: number;
        items?: Array<{ count?: number }>;
        recent?: Array<{
            id?: number;
            title?: string;
            message?: string;
            type?: string;
            is_read?: boolean;
            published_at?: string | null;
        }>;
    };
    admin_notifications?: {
        total?: number;
        items?: Array<{ count?: number }>;
        recent?: Array<{
            id?: number;
            title?: string;
            message?: string;
            type?: string;
            is_read?: boolean;
            published_at?: string | null;
        }>;
    };
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    auth: Auth;
    [key: string]: unknown;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    avatar_url?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
