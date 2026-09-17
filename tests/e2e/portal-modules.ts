/**
 * Bit flags mirror App\Http\ViewComposers\PortalComposer module constants.
 */
export const PortalModule = {
    RECURRING_INVOICES: 1,
    CREDITS: 2,
    QUOTES: 4,
    TASKS: 8,
    EXPENSES: 16,
    PROJECTS: 32,
    INVOICES: 4096,
} as const;

export const ALL_ENABLED_MODULES = 65535;

export const ALWAYS_VISIBLE_SIDEBAR_IDS = [
    'payments',
    'payment_methods',
    'documents',
    'statement',
    'subscriptions',
] as const;

export const MODULE_SIDEBAR_LINKS = {
    dashboard: {
        id: 'dashboard',
        path: '/client/dashboard',
        clientSetting: 'enable_client_portal_dashboard',
    },
    invoices: {
        id: 'invoices',
        path: '/client/invoices',
        module: PortalModule.INVOICES,
    },
    recurring_invoices: {
        id: 'recurring_invoices',
        path: '/client/recurring_invoices',
        module: PortalModule.RECURRING_INVOICES,
    },
    quotes: {
        id: 'quotes',
        path: '/client/quotes',
        module: PortalModule.QUOTES,
    },
    credits: {
        id: 'credits',
        path: '/client/credits',
        module: PortalModule.CREDITS,
    },
    tasks: {
        id: 'tasks',
        path: '/client/tasks',
        clientSetting: 'enable_client_portal_tasks',
    },
    projects: {
        id: 'projects',
        path: '/client/projects',
        clientSetting: 'enable_client_portal_tasks',
    },
    pre_payment: {
        id: 'pre_payment',
        path: '/client/pre_payments',
        clientSetting: 'client_initiated_payments',
    },
} as const;

export type ModuleSidebarKey = keyof typeof MODULE_SIDEBAR_LINKS;

export function enabledModulesWithout(...flags: number[]): number {
    return flags.reduce(
        (mask, flag) => mask & ~flag,
        ALL_ENABLED_MODULES,
    );
}

export function expectedSidebarIds(options: {
    enabledModules?: number;
    clientSettings?: Record<string, unknown>;
}): string[] {
    const enabledModules = options.enabledModules ?? ALL_ENABLED_MODULES;
    const clientSettings = options.clientSettings ?? {};
    const ids: string[] = [];

    if (clientSettings.enable_client_portal_dashboard !== false) {
        ids.push(MODULE_SIDEBAR_LINKS.dashboard.id);
    }

    if (enabledModules & PortalModule.INVOICES) {
        ids.push(MODULE_SIDEBAR_LINKS.invoices.id);
    }

    if (enabledModules & PortalModule.RECURRING_INVOICES) {
        ids.push(MODULE_SIDEBAR_LINKS.recurring_invoices.id);
    }

    ids.push('payments');

    if (enabledModules & PortalModule.QUOTES) {
        ids.push(MODULE_SIDEBAR_LINKS.quotes.id);
    }

    if (enabledModules & PortalModule.CREDITS) {
        ids.push(MODULE_SIDEBAR_LINKS.credits.id);
    }

    ids.push('payment_methods', 'documents');

    if (clientSettings.enable_client_portal_tasks !== false) {
        ids.push(
            MODULE_SIDEBAR_LINKS.tasks.id,
            MODULE_SIDEBAR_LINKS.projects.id,
        );
    }

    ids.push('statement', 'subscriptions');

    if (clientSettings.client_initiated_payments !== false) {
        ids.push(MODULE_SIDEBAR_LINKS.pre_payment.id);
    }

    return ids;
}
