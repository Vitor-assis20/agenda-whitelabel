import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TenantSwitcher } from '@/components/tenant-switcher';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BriefcaseBusiness, CalendarDays, ExternalLink, Users } from 'lucide-react';
import AppLogo from './app-logo';

const agendaItem: NavItem = { title: 'Agenda', url: '/painel/agenda', icon: CalendarDays };

// Cadastros: só dono e administradores (o servidor também barra a equipe).
const managementItems: NavItem[] = [
    { title: 'Serviços', url: '/painel/servicos', icon: BriefcaseBusiness },
    { title: 'Profissionais', url: '/painel/profissionais', icon: Users },
];

export function AppSidebar() {
    const { panel } = usePage<SharedData>().props;

    const canManage = panel && panel.current.role !== 'staff';
    const mainNavItems = canManage ? [agendaItem, ...managementItems] : [agendaItem];

    const footerNavItems: NavItem[] = panel ? [{ title: 'Página pública', url: panel.current.public_url, icon: ExternalLink }] : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/painel/agenda" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <TenantSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
