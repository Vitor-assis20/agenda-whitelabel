import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Clock, LoaderCircle, Pencil, Plus, Trash2, X } from 'lucide-react';
import { type FormEventHandler, type ReactNode, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { WEEKDAYS } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type ManagedProfessional, type ServiceOption, type WorkingHour } from '@/types/panel';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Profissionais', href: '/painel/profissionais' }];

// Semana começando na segunda, como o brasileiro lê uma agenda.
const WEEK_ORDER = [1, 2, 3, 4, 5, 6, 0];

type ProfessionalForm = {
    name: string;
    email: string;
    bio: string;
    is_active: boolean;
    service_ids: number[];
};

type Editing = { kind: 'profile' | 'schedule'; professional: ManagedProfessional | null } | null;

function FormDialog({ title, description, onClose, children }: { title: string; description?: string; onClose: () => void; children: ReactNode }) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

function SaveFooter({ processing, onClose }: { processing: boolean; onClose: () => void }) {
    return (
        <DialogFooter>
            <Button type="button" variant="ghost" onClick={onClose}>
                Cancelar
            </Button>
            <Button type="submit" disabled={processing}>
                {processing && <LoaderCircle className="size-4 animate-spin" />}
                Salvar
            </Button>
        </DialogFooter>
    );
}

function ProfileDialog({ professional, services, onClose }: { professional: ManagedProfessional | null; services: ServiceOption[]; onClose: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm<ProfessionalForm>({
        name: professional?.name ?? '',
        email: professional?.email ?? '',
        bio: professional?.bio ?? '',
        is_active: professional?.is_active ?? true,
        service_ids: professional?.service_ids ?? [],
    });

    const toggleService = (id: number, checked: boolean) =>
        setData('service_ids', checked ? [...data.service_ids, id] : data.service_ids.filter((serviceId) => serviceId !== id));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (professional) put(route('panel.professionals.update', { professional: professional.id }), options);
        else post(route('panel.professionals.store'), options);
    };

    return (
        <FormDialog title={professional ? 'Editar profissional' : 'Novo profissional'} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nome</Label>
                    <Input id="name" required autoFocus value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">E-mail (opcional)</Label>
                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="bio">Apresentação (opcional)</Label>
                    <Textarea id="bio" value={data.bio} onChange={(e) => setData('bio', e.target.value)} />
                    <InputError message={errors.bio} />
                </div>

                <fieldset className="grid gap-2">
                    <legend className="mb-2 text-sm font-medium">Serviços que atende</legend>
                    {services.length === 0 && <p className="text-muted-foreground text-sm">Cadastre serviços primeiro.</p>}
                    {services.map((service) => (
                        <div key={service.id} className="flex items-center gap-2">
                            <Checkbox
                                id={`service-${service.id}`}
                                checked={data.service_ids.includes(service.id)}
                                onCheckedChange={(checked) => toggleService(service.id, checked === true)}
                            />
                            <Label htmlFor={`service-${service.id}`} className="font-normal">
                                {service.name}
                                {!service.is_active && <span className="text-muted-foreground"> (inativo)</span>}
                            </Label>
                        </div>
                    ))}
                    <InputError message={errors.service_ids} />
                </fieldset>

                <div className="flex items-center gap-2">
                    <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                    <Label htmlFor="is_active">Recebe agendamentos</Label>
                </div>

                <SaveFooter processing={processing} onClose={onClose} />
            </form>
        </FormDialog>
    );
}

function ScheduleDialog({ professional, onClose }: { professional: ManagedProfessional; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm<{ hours: WorkingHour[] }>({ hours: professional.working_hours });
    const fieldErrors = errors as Record<string, string>;

    // Mantém o índice original de cada intervalo: os erros vêm como hours.{índice}.campo.
    const indexed = data.hours.map((hours, index) => ({ ...hours, index }));

    const update = (index: number, field: 'starts_at' | 'ends_at', value: string) =>
        setData(
            'hours',
            data.hours.map((hours, i) => (i === index ? { ...hours, [field]: value } : hours)),
        );
    const add = (weekday: number, starts_at = '09:00', ends_at = '18:00') => setData('hours', [...data.hours, { weekday, starts_at, ends_at }]);
    const remove = (index: number) => setData('hours', data.hours.filter((_, i) => i !== index));
    const clearDay = (weekday: number) => setData('hours', data.hours.filter((hours) => hours.weekday !== weekday));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('panel.professionals.working-hours', { professional: professional.id }), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormDialog title={`Expediente de ${professional.name}`} description="Horários no fuso do seu negócio. Use mais de um intervalo para pausas." onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="divide-y rounded-lg border">
                    {WEEK_ORDER.map((weekday) => {
                        const intervals = indexed.filter((hours) => hours.weekday === weekday);
                        const works = intervals.length > 0;

                        return (
                            <div key={weekday} className="flex flex-col gap-2 p-3 sm:flex-row sm:items-start">
                                <div className="flex w-24 shrink-0 items-center gap-2 pt-2">
                                    <Checkbox
                                        id={`day-${weekday}`}
                                        checked={works}
                                        onCheckedChange={(checked) => (checked ? add(weekday) : clearDay(weekday))}
                                    />
                                    <Label htmlFor={`day-${weekday}`}>{WEEKDAYS[weekday]}</Label>
                                </div>

                                {works ? (
                                    <div className="flex-1 space-y-2">
                                        {intervals.map((hours) => (
                                            <div key={hours.index}>
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        type="time"
                                                        aria-label="Início"
                                                        required
                                                        value={hours.starts_at}
                                                        onChange={(e) => update(hours.index, 'starts_at', e.target.value)}
                                                    />
                                                    <span className="text-muted-foreground">até</span>
                                                    <Input
                                                        type="time"
                                                        aria-label="Fim"
                                                        required
                                                        value={hours.ends_at}
                                                        onChange={(e) => update(hours.index, 'ends_at', e.target.value)}
                                                    />
                                                    <Button type="button" variant="ghost" size="icon" aria-label="Remover intervalo" onClick={() => remove(hours.index)}>
                                                        <X />
                                                    </Button>
                                                </div>
                                                <InputError message={fieldErrors[`hours.${hours.index}.starts_at`] ?? fieldErrors[`hours.${hours.index}.ends_at`]} />
                                            </div>
                                        ))}
                                        <Button type="button" variant="link" size="sm" className="h-auto p-0" onClick={() => add(weekday, '14:00', '18:00')}>
                                            + Adicionar intervalo
                                        </Button>
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground pt-2 text-sm">Não atende</span>
                                )}
                            </div>
                        );
                    })}
                </div>
                <InputError message={fieldErrors.hours} />

                <SaveFooter processing={processing} onClose={onClose} />
            </form>
        </FormDialog>
    );
}

function ScheduleSummary({ hours }: { hours: WorkingHour[] }) {
    if (hours.length === 0) {
        return <span className="text-amber-600 dark:text-amber-400">sem expediente cadastrado</span>;
    }

    return (
        <span>
            {WEEK_ORDER.filter((weekday) => hours.some((h) => h.weekday === weekday))
                .map(
                    (weekday) =>
                        `${WEEKDAYS[weekday]} ${hours
                            .filter((h) => h.weekday === weekday)
                            .map((h) => `${h.starts_at}–${h.ends_at}`)
                            .join(', ')}`,
                )
                .join(' · ')}
        </span>
    );
}

export default function Professionals({ professionals, services }: { professionals: ManagedProfessional[]; services: ServiceOption[] }) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [editing, setEditing] = useState<Editing>(null);
    const close = () => setEditing(null);
    const serviceNames = new Map(services.map((service) => [service.id, service.name]));

    const destroy = (professional: ManagedProfessional) => {
        if (window.confirm(`Excluir ${professional.name}?`)) {
            router.delete(route('panel.professionals.destroy', { professional: professional.id }), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profissionais" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Profissionais</h1>
                        <p className="text-muted-foreground text-sm">Quem atende, quais serviços e em que horários.</p>
                    </div>
                    <Button onClick={() => setEditing({ kind: 'profile', professional: null })}>
                        <Plus /> Novo profissional
                    </Button>
                </div>

                <InputError message={errors.professional} />

                {professionals.length === 0 ? (
                    <p className="text-muted-foreground">Nenhum profissional cadastrado ainda.</p>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {professionals.map((professional) => (
                            <li key={professional.id} className={cn('flex flex-wrap items-start gap-4 p-4', !professional.is_active && 'opacity-60')}>
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex items-center gap-2 font-medium">
                                        {professional.name}
                                        {!professional.is_active && <span className="bg-muted rounded-full px-2 py-0.5 text-xs">Inativo</span>}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {professional.service_ids.length === 0 ? (
                                            <span className="text-amber-600 dark:text-amber-400">nenhum serviço</span>
                                        ) : (
                                            professional.service_ids.map((id) => serviceNames.get(id)).join(', ')
                                        )}
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        <ScheduleSummary hours={professional.working_hours} />
                                    </div>
                                </div>

                                <div className="flex gap-1">
                                    <Button variant="outline" size="sm" onClick={() => setEditing({ kind: 'schedule', professional })}>
                                        <Clock /> Expediente
                                    </Button>
                                    <Button variant="ghost" size="icon" aria-label="Editar" onClick={() => setEditing({ kind: 'profile', professional })}>
                                        <Pencil />
                                    </Button>
                                    {professional.deletable && (
                                        <Button variant="ghost" size="icon" aria-label="Excluir" onClick={() => destroy(professional)}>
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {editing?.kind === 'profile' && (
                <ProfileDialog key={editing.professional?.id ?? 'new'} professional={editing.professional} services={services} onClose={close} />
            )}
            {editing?.kind === 'schedule' && editing.professional && (
                <ScheduleDialog key={editing.professional.id} professional={editing.professional} onClose={close} />
            )}
        </AppLayout>
    );
}
