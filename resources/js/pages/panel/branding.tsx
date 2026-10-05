import { Head, useForm, usePage } from '@inertiajs/react';
import { Check, ExternalLink, LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useEffect, useMemo } from 'react';

import InputError from '@/components/input-error';
import { brandStyle, TenantHeader } from '@/components/tenant-brand';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { isHexColor } from '@/lib/color';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type Tenant } from '@/types/booking';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Marca', href: '/painel/marca' }];

const PRESETS = ['#b45309', '#0f766e', '#1d4ed8', '#7c3aed', '#be123c', '#111827'];

type BrandingForm = {
    name: string;
    primary_color: string;
    logo: File | null;
    remove_logo: boolean;
};

/** Miniatura da página pública com a marca que está sendo editada. */
function PublicPagePreview({ tenant }: { tenant: Pick<Tenant, 'name' | 'branding'> }) {
    return (
        <div className="bg-muted/40 overflow-hidden rounded-xl border" style={brandStyle(tenant.branding.primary_color)}>
            <TenantHeader tenant={tenant} />
            <div className="space-y-4 p-4">
                <div className="text-lg font-semibold">Agende seu horário</div>
                <div className="border-primary ring-primary bg-background rounded-lg border p-3 ring-2">
                    <div className="font-medium">Serviço de exemplo</div>
                    <div className="text-muted-foreground text-sm">30 min · R$ 50,00</div>
                </div>
                <div className="grid grid-cols-4 gap-2">
                    {['09:00', '09:30', '10:00', '10:30'].map((time, i) => (
                        <Button key={time} type="button" size="sm" variant={i === 1 ? 'default' : 'outline'} tabIndex={-1}>
                            {time}
                        </Button>
                    ))}
                </div>
                <Button type="button" className="w-full" tabIndex={-1}>
                    Confirmar agendamento
                </Button>
            </div>
        </div>
    );
}

export default function Branding({ business }: { business: { name: string; slug: string; branding: Tenant['branding'] } }) {
    const { panel } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm<BrandingForm>({
        name: business.name,
        primary_color: business.branding.primary_color ?? PRESETS[0],
        logo: null,
        remove_logo: false,
    });

    // Prévia do arquivo escolhido antes de enviar.
    const selectedLogoUrl = useMemo(() => (data.logo ? URL.createObjectURL(data.logo) : null), [data.logo]);
    useEffect(() => () => void (selectedLogoUrl && URL.revokeObjectURL(selectedLogoUrl)), [selectedLogoUrl]);

    const logoUrl = selectedLogoUrl ?? (data.remove_logo ? null : business.branding.logo_url);
    const previewColor = isHexColor(data.primary_color) ? data.primary_color : business.branding.primary_color;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('panel.branding.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setData((form) => ({ ...form, logo: null, remove_logo: false })),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Marca" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Marca</h1>
                        <p className="text-muted-foreground text-sm">Como seu negócio aparece para os clientes.</p>
                    </div>
                    {panel && (
                        <Button variant="outline" asChild>
                            <a href={panel.current.public_url} target="_blank" rel="noopener noreferrer">
                                <ExternalLink /> Ver página pública
                            </a>
                        </Button>
                    )}
                </div>

                <div className="grid gap-8 lg:grid-cols-2">
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nome do negócio</Label>
                            <Input id="name" required value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="color">Cor principal</Label>
                            <div className="flex flex-wrap items-center gap-2">
                                {PRESETS.map((color) => (
                                    <button
                                        key={color}
                                        type="button"
                                        aria-label={`Usar ${color}`}
                                        onClick={() => setData('primary_color', color)}
                                        className={cn(
                                            'flex size-8 items-center justify-center rounded-full border-2 border-transparent text-white',
                                            data.primary_color.toLowerCase() === color && 'border-foreground',
                                        )}
                                        style={{ backgroundColor: color }}
                                    >
                                        {data.primary_color.toLowerCase() === color && <Check className="size-4" />}
                                    </button>
                                ))}
                                <input
                                    type="color"
                                    aria-label="Escolher outra cor"
                                    value={previewColor ?? PRESETS[0]}
                                    onChange={(e) => setData('primary_color', e.target.value)}
                                    className="size-8 cursor-pointer rounded-full border bg-transparent"
                                />
                                <Input
                                    id="color"
                                    className="w-28 font-mono"
                                    maxLength={7}
                                    value={data.primary_color}
                                    onChange={(e) => setData('primary_color', e.target.value)}
                                />
                            </div>
                            <InputError message={errors.primary_color} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="logo">Logo</Label>
                            <Input
                                id="logo"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) => setData((form) => ({ ...form, logo: e.target.files?.[0] ?? null, remove_logo: false }))}
                            />
                            <p className="text-muted-foreground text-xs">PNG, JPG ou WebP, até 1 MB, com pelo menos 64×64 pixels. Imagens quadradas ficam melhores.</p>
                            {business.branding.logo_url && !data.logo && (
                                <label className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" checked={data.remove_logo} onChange={(e) => setData('remove_logo', e.target.checked)} />
                                    Remover o logo atual (usar as iniciais)
                                </label>
                            )}
                            <InputError message={errors.logo} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={processing}>
                                {processing && <LoaderCircle className="size-4 animate-spin" />}
                                Salvar
                            </Button>
                            {recentlySuccessful && <span className="text-muted-foreground text-sm">Salvo.</span>}
                        </div>
                    </form>

                    <div className="space-y-2">
                        <div className="text-muted-foreground text-sm">Prévia</div>
                        <PublicPagePreview tenant={{ name: data.name || business.name, branding: { primary_color: previewColor, logo_url: logoUrl } }} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
