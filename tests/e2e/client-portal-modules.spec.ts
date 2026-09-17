import {
    createAndLogInClient,
    expectPortalRouteForbidden,
    expectPortalRouteRedirects,
    expectSidebarLinkIds,
    expectSidebarMissing,
} from './client-portal-helpers';
import { expect, test } from './fixtures';
import {
    ALL_ENABLED_MODULES,
    enabledModulesWithout,
    expectedSidebarIds,
    MODULE_SIDEBAR_LINKS,
    PortalModule,
} from './portal-modules';

test.describe('Client portal module visibility', () => {
    test('hides company modules when they are disabled on the company', async ({
        api,
        companyGuard,
        page,
    }) => {
        const disabledModules = enabledModulesWithout(
            PortalModule.INVOICES,
            PortalModule.RECURRING_INVOICES,
            PortalModule.QUOTES,
            PortalModule.CREDITS,
        );

        await companyGuard.update({ enabled_modules: disabledModules });
        await createAndLogInClient(api, page);

        const expectedIds = expectedSidebarIds({
            enabledModules: disabledModules,
        });

        await expectSidebarLinkIds(page, expectedIds);
        await expectSidebarMissing(page, [
            MODULE_SIDEBAR_LINKS.invoices.id,
            MODULE_SIDEBAR_LINKS.recurring_invoices.id,
            MODULE_SIDEBAR_LINKS.quotes.id,
            MODULE_SIDEBAR_LINKS.credits.id,
        ]);

        await expectPortalRouteForbidden(page, MODULE_SIDEBAR_LINKS.invoices.path);
        await expectPortalRouteForbidden(
            page,
            MODULE_SIDEBAR_LINKS.recurring_invoices.path,
        );
        await expectPortalRouteForbidden(page, MODULE_SIDEBAR_LINKS.quotes.path);
        await expectPortalRouteForbidden(page, MODULE_SIDEBAR_LINKS.credits.path);
    });

    test('hides client portal features controlled by client settings', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page, {
            settings: {
                enable_client_portal_dashboard: false,
                enable_client_portal_tasks: false,
                client_initiated_payments: false,
            },
        });

        const expectedIds = expectedSidebarIds({
            enabledModules: ALL_ENABLED_MODULES,
            clientSettings: {
                enable_client_portal_dashboard: false,
                enable_client_portal_tasks: false,
                client_initiated_payments: false,
            },
        });

        await expectSidebarLinkIds(page, expectedIds);
        await expectSidebarMissing(page, [
            MODULE_SIDEBAR_LINKS.dashboard.id,
            MODULE_SIDEBAR_LINKS.tasks.id,
            MODULE_SIDEBAR_LINKS.projects.id,
            MODULE_SIDEBAR_LINKS.pre_payment.id,
        ]);

        await expectPortalRouteForbidden(page, MODULE_SIDEBAR_LINKS.tasks.path);
        await expectPortalRouteRedirects(
            page,
            MODULE_SIDEBAR_LINKS.pre_payment.path,
            /\/client\/(dashboard|invoices)(?:\/|$)/,
        );
    });

    test('keeps core navigation when only optional modules are disabled', async ({
        api,
        companyGuard,
        page,
    }) => {
        const disabledModules = enabledModulesWithout(
            PortalModule.INVOICES,
            PortalModule.QUOTES,
        );

        await companyGuard.update({ enabled_modules: disabledModules });
        await createAndLogInClient(api, page, {
            settings: {
                enable_client_portal_dashboard: true,
                enable_client_portal_tasks: true,
                client_initiated_payments: true,
            },
        });

        const expectedIds = expectedSidebarIds({
            enabledModules: disabledModules,
            clientSettings: {
                enable_client_portal_dashboard: true,
                enable_client_portal_tasks: true,
                client_initiated_payments: true,
            },
        });

        await expectSidebarLinkIds(page, expectedIds);
    });

    test('shows the full sidebar when every module and client feature is enabled', async ({
        api,
        companyGuard,
        page,
    }) => {
        await companyGuard.update({ enabled_modules: ALL_ENABLED_MODULES });
        await createAndLogInClient(api, page);

        const expectedIds = expectedSidebarIds({
            enabledModules: ALL_ENABLED_MODULES,
            clientSettings: {
                enable_client_portal_dashboard: true,
                enable_client_portal_tasks: true,
                client_initiated_payments: true,
            },
        });

        await expectSidebarLinkIds(page, expectedIds);
        expect(expectedIds).toHaveLength(13);
    });
});
