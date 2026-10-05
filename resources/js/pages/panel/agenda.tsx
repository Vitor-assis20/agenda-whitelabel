import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarOff, ChevronLeft, ChevronRight, Phone } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type AgendaAppointment, type AgendaDate, type AgendaProfessional, type AgendaSummary, type AppointmentStatus } from '@/types/panel';

interface AgendaProps {
    date: AgendaDate;
    summary: AgendaSummary;
    professionals: AgendaProfessional[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Agenda', href: '/painel/agenda' }];

const STATUS: Record<AppointmentStatus, { label: string; className: string }> = {
    pending: { label: 'Pendente', className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' },
    confirmed: { label: 'Confirmado', className: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' },
    completed: { label: 'Concluído', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' },
    no_show: { label: 'Faltou', className: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' },
    cancelled: { label: 'Cancelado', className: 'bg-muted text-muted-foreground' },
};

const ACTIONS: Partial<Record<AppointmentStatus, { label: string; variant: 'default' | 'outline' | 'ghost'; confirm?: string }>> = {
    confirmed: { label: 'Confirmar', variant: 'default' },
    completed: { label: 'Concluir', variant: 'default' },
    no_show: { label: 'Faltou', variant: 'outline', confirm: 'Marcar que o cliente faltou?' },
    cancelled: { label: 'Cancelar', variant: 'ghost', confirm: 'Cancelar este agendamento? O horário volta a ficar livre.' },
};

function changeStatus(appointment: AgendaAppointment, status: AppointmentStatus) {
    const confirmation = ACTIONS[status]?.confirm;
    if (confirmation && !window.confirm(confirmation)) return;

    router.patch(route('panel.appointments.status', { appointment: appointment.id }), { status }, { preserveScroll: true });
}

function SummaryCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl border p-4">
            <div className="text-muted-foreground text-sm">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}

function AppointmentRow({ appointment }: { appointment: AgendaAppointment }) {
    const inactive = appointment.status === 'cancelled' || appointment.status === 'no_show';

    return (
        <li className={cn('flex flex-col gap-3 p-4 sm:flex-row sm:items-start', inactive && 'opacity-60')}>
            <div className="w-24 shrink-0 font-medium tabular-nums">
                {appointment.starts_at}
                <span className="text-muted-foreground block text-xs font-normal">até {appointment.ends_at}</span>
            </div>

            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className={cn('font-medium', appointment.status === 'cancelled' && 'line-through')}>{appointment.customer.name}</span>
                    <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', STATUS[appointment.status].className)}>
                        {STATUS[appointment.status].label}
                    </span>
                </div>
                <div className="text-muted-foreground text-sm">
                    {appointment.service} · {formatPrice(appointment.price_cents)}
                </div>
                {appointment.customer.phone && (
                    <a
                        href={`tel:${appointment.customer.phone}`}
                        className="text-muted-foreground inline-flex items-center gap-1 text-sm hover:underline"
                    >
                        <Phone className="size-3" /> {appointment.customer.phone}
                    </a>
                )}
                {appointment.notes && <p className="text-sm italic">“{appointment.notes}”</p>}
            </div>

            {appointment.actions.length > 0 && (
                <div className="flex flex-wrap gap-2 sm:justify-end">
                    {appointment.actions.map((status) => (
                        <Button
                            key={status}
                            size="sm"
                            variant={ACTIONS[status]?.variant ?? 'outline'}
                            onClick={() => changeStatus(appointment, status)}
                        >
                            {ACTIONS[status]?.label ?? STATUS[status].label}
                        </Button>
                    ))}
                </div>
            )}
        </li>
    );
}

export default function Agenda({ date, summary, professionals }: AgendaProps) {
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    // Aparece quem tem atendimento ou folga no dia.
    const visible = professionals.filter((professional) => professional.appointments.length > 0 || professional.time_offs.length > 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Agenda" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{date.label}</h1>
                        {date.is_today && <p className="text-muted-foreground text-sm">Hoje</p>}
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={route('panel.agenda', { date: date.previous })} aria-label="Dia anterior" preserveScroll>
                                <ChevronLeft />
                            </Link>
                        </Button>
                        {!date.is_today && (
                            <Button variant="outline" asChild>
                                <Link href={route('panel.agenda')}>Hoje</Link>
                            </Button>
                        )}
                        <Input
                            type="date"
                            aria-label="Ir para a data"
                            className="w-auto"
                            value={date.value}
                            onChange={(e) => e.target.value && router.get(route('panel.agenda'), { date: e.target.value })}
                        />
                        <Button variant="outline" size="icon" asChild>
                            <Link href={route('panel.agenda', { date: date.next })} aria-label="Próximo dia" preserveScroll>
                                <ChevronRight />
                            </Link>
                        </Button>
                    </div>
                </div>

                <InputError message={errors.status} />

                <div className="grid gap-4 sm:grid-cols-3">
                    <SummaryCard label="Agendamentos" value={summary.appointments} />
                    <SummaryCard label="Concluídos" value={summary.completed} />
                    <SummaryCard label="Faturamento previsto" value={formatPrice(summary.expected_revenue_cents)} />
                </div>

                {professionals.length === 0 ? (
                    <p className="text-muted-foreground">Nenhum profissional ativo vinculado à sua conta.</p>
                ) : visible.length === 0 ? (
                    <p className="text-muted-foreground">Nenhum agendamento neste dia.</p>
                ) : (
                    visible.map((professional) => (
                        <section key={professional.id} className="rounded-xl border">
                            <h2 className="border-b px-4 py-3 font-semibold">
                                {professional.name}
                                <span className="text-muted-foreground ml-2 text-sm font-normal">
                                    {professional.appointments.length} atendimento(s)
                                </span>
                            </h2>
                            {professional.time_offs.map((timeOff, index) => (
                                <div key={index} className="bg-muted/60 text-muted-foreground flex items-center gap-2 border-b px-4 py-2 text-sm">
                                    <CalendarOff className="size-4" />
                                    <span className="text-foreground font-medium">Folga · {timeOff.period}</span>
                                    {timeOff.reason && <span>· {timeOff.reason}</span>}
                                </div>
                            ))}
                            <ul className="divide-y">
                                {professional.appointments.map((appointment) => (
                                    <AppointmentRow key={appointment.id} appointment={appointment} />
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
