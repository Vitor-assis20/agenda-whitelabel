import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { type CSSProperties, type PropsWithChildren } from 'react';

function initials(name: string): string {
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join('');
}

/**
 * Layout das páginas públicas: aplica a marca do negócio sobrescrevendo as
 * variáveis de tema, então todos os componentes de UI herdam a cor.
 */
export default function TenantLayout({ children }: PropsWithChildren) {
    const tenant = usePage<SharedData>().props.tenant!;
    const color = tenant.branding.primary_color;

    const brand = (color ? { '--primary': color, '--primary-foreground': '#ffffff', '--ring': color } : {}) as CSSProperties;

    return (
        <div className="bg-muted/40 min-h-svh" style={brand}>
            <header className="bg-background border-b">
                <div className="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4">
                    {tenant.branding.logo_url ? (
                        <img src={tenant.branding.logo_url} alt="" className="size-10 rounded-full object-cover" />
                    ) : (
                        <div className="bg-primary text-primary-foreground flex size-10 items-center justify-center rounded-full text-sm font-semibold">
                            {initials(tenant.name)}
                        </div>
                    )}
                    <span className="text-lg font-semibold">{tenant.name}</span>
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-8">{children}</main>
        </div>
    );
}
