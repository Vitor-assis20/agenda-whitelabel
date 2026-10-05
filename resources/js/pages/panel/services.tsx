import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { centsToInput, formatDuration, formatPrice, inputToCents } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type ManagedService } from '@/types/panel';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Serviços', href: '/painel/servicos' }];

type ServiceForm = {
    name: string;
    description: string;
    duration_minutes: number;
    price: string;
    is_active: boolean;
};

function ServiceDialog({ service, onClose }: { service: ManagedService | null; onClose: () => void }) {
    const { data, setData, transform, post, put, processing, errors } = useForm<ServiceForm>({
        name: service?.name ?? '',
        description: service?.description ?? '',
        duration_minutes: service?.duration_minutes ?? 30,
        price: service ? centsToInput(service.price_cents) : '',
        is_active: service?.is_active ?? true,
    });
    const fieldErrors = errors as Record<string, string>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform(({ price, ...rest }) => ({ ...rest, price_cents: inputToCents(price) }));

        const options = { preserveScroll: true, onSuccess: onClose };
        if (service) put(route('panel.services.update', { service: service.id }), options);
        else post(route('panel.services.store'), options);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{service ? 'Editar serviço' : 'Novo serviço'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nome</Label>
                        <Input id="name" required autoFocus value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Descrição (opcional)</Label>
                        <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="duration">Duração (minutos)</Label>
                            <Input
                                id="duration"
                                type="number"
                                min={5}
                                max={480}
                                step={5}
                                required
                                value={data.duration_minutes}
                                onChange={(e) => setData('duration_minutes', Number(e.target.value))}
                            />
                            <InputError message={errors.duration_minutes} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="price">Preço (R$)</Label>
                            <Input
                                id="price"
                                inputMode="decimal"
                                placeholder="50,00"
                                required
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                            />
                            <InputError message={fieldErrors.price_cents} />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                        <Label htmlFor="is_active">Disponível para agendamento</Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            Salvar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Services({ services }: { services: ManagedService[] }) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    // undefined: fechado; null: novo serviço.
    const [editing, setEditing] = useState<ManagedService | null | undefined>(undefined);

    const destroy = (service: ManagedService) => {
        if (window.confirm(`Excluir o serviço "${service.name}"?`)) {
            router.delete(route('panel.services.destroy', { service: service.id }), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Serviços" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Serviços</h1>
                        <p className="text-muted-foreground text-sm">O que seus clientes podem agendar.</p>
                    </div>
                    <Button onClick={() => setEditing(null)}>
                        <Plus /> Novo serviço
                    </Button>
                </div>

                <InputError message={errors.service} />

                {services.length === 0 ? (
                    <p className="text-muted-foreground">Nenhum serviço cadastrado ainda.</p>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {services.map((service) => (
                            <li key={service.id} className={cn('flex flex-wrap items-center gap-4 p-4', !service.is_active && 'opacity-60')}>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2 font-medium">
                                        {service.name}
                                        {!service.is_active && <span className="bg-muted rounded-full px-2 py-0.5 text-xs">Inativo</span>}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {formatDuration(service.duration_minutes)} · {formatPrice(service.price_cents)} ·{' '}
                                        {service.professionals_count === 0 ? (
                                            <span className="text-amber-600 dark:text-amber-400">nenhum profissional atende</span>
                                        ) : (
                                            `${service.professionals_count} profissional(is)`
                                        )}
                                    </div>
                                </div>

                                <div className="flex gap-1">
                                    <Button variant="ghost" size="icon" aria-label="Editar" onClick={() => setEditing(service)}>
                                        <Pencil />
                                    </Button>
                                    {service.deletable && (
                                        <Button variant="ghost" size="icon" aria-label="Excluir" onClick={() => destroy(service)}>
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {editing !== undefined && <ServiceDialog key={editing?.id ?? 'new'} service={editing} onClose={() => setEditing(undefined)} />}
        </AppLayout>
    );
}
