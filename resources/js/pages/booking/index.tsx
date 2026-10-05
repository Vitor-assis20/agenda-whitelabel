import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler, type ReactNode, useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDuration, formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AvailableSlot, type BookableDate, type BookableService } from '@/types/booking';

interface BookingProps {
    services: BookableService[];
    dates: BookableDate[];
}

// "type" em vez de "interface": o useForm exige um tipo com assinatura de índice implícita.
type BookingForm = {
    service_id: number | null;
    professional_id: number | null;
    starts_at: string;
    name: string;
    email: string;
    phone: string;
};

function Step({ number, title, children }: { number: number; title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h2 className="flex items-center gap-2 font-semibold">
                <span className="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-full text-xs">{number}</span>
                {title}
            </h2>
            {children}
        </section>
    );
}

/**
 * Busca os horários livres sempre que serviço, profissional ou dia mudam.
 * Respostas atrasadas de uma seleção anterior são descartadas.
 */
function useAvailableSlots(serviceId: number | null, professionalId: number | null, date: string | null) {
    const [slots, setSlots] = useState<AvailableSlot[]>([]);
    const [loading, setLoading] = useState(false);
    const [version, setVersion] = useState(0);

    useEffect(() => {
        if (!serviceId || !professionalId || !date) {
            setSlots([]);
            return;
        }

        const controller = new AbortController();
        const params = new URLSearchParams({ service_id: String(serviceId), professional_id: String(professionalId), date });
        setLoading(true);

        fetch(`/horarios?${params}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then((response) => (response.ok ? response.json() : { slots: [] }))
            .then((body: { slots: AvailableSlot[] }) => setSlots(body.slots))
            .catch((error: Error) => {
                if (error.name !== 'AbortError') setSlots([]);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [serviceId, professionalId, date, version]);

    return { slots, loading, refresh: () => setVersion((v) => v + 1) };
}

export default function Booking({ services, dates }: BookingProps) {
    const [date, setDate] = useState<string | null>(null);

    const { data, setData, post, processing, errors } = useForm<BookingForm>({
        service_id: null,
        professional_id: null,
        starts_at: '',
        name: '',
        email: '',
        phone: '',
    });

    const service = services.find((s) => s.id === data.service_id);
    const { slots, loading, refresh } = useAvailableSlots(data.service_id, data.professional_id, date);
    const selectedSlot = slots.find((slot) => slot.starts_at === data.starts_at);

    const selectService = (selected: BookableService) =>
        setData((form) => ({
            ...form,
            service_id: selected.id,
            // Com um único profissional, a escolha é óbvia.
            professional_id: selected.professionals.length === 1 ? selected.professionals[0].id : null,
            starts_at: '',
        }));

    const selectProfessional = (id: number) => setData((form) => ({ ...form, professional_id: id, starts_at: '' }));

    const selectDate = (value: string) => {
        setDate(value);
        setData('starts_at', '');
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/agendar', {
            preserveScroll: true,
            onError: (errors) => {
                // Horário ocupado por outra pessoa: atualiza a grade para escolher outro.
                if (errors.starts_at) {
                    setData('starts_at', '');
                    refresh();
                }
            },
        });
    };

    return (
        <TenantLayout>
            <Head title="Agendar horário" />

            <div className="space-y-8">
                <div>
                    <h1 className="text-2xl font-semibold">Agende seu horário</h1>
                    <p className="text-muted-foreground">Escolha o serviço, o profissional e o melhor horário para você.</p>
                </div>

                {services.length === 0 ? (
                    <p className="text-muted-foreground">Nenhum serviço disponível para agendamento no momento.</p>
                ) : (
                    <Step number={1} title="Serviço">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {services.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => selectService(item)}
                                    aria-pressed={item.id === data.service_id}
                                    className={cn(
                                        'bg-background rounded-lg border p-4 text-left transition',
                                        item.id === data.service_id ? 'border-primary ring-primary ring-2' : 'hover:border-primary/50',
                                    )}
                                >
                                    <div className="font-medium">{item.name}</div>
                                    {item.description && <p className="text-muted-foreground mt-1 line-clamp-2 text-sm">{item.description}</p>}
                                    <div className="mt-3 text-sm">
                                        {formatDuration(item.duration_minutes)} · <span className="font-medium">{formatPrice(item.price_cents)}</span>
                                    </div>
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.service_id} />
                    </Step>
                )}

                {service && (
                    <Step number={2} title="Profissional">
                        <div className="flex flex-wrap gap-2">
                            {service.professionals.map((professional) => (
                                <Button
                                    key={professional.id}
                                    type="button"
                                    variant={professional.id === data.professional_id ? 'default' : 'outline'}
                                    onClick={() => selectProfessional(professional.id)}
                                >
                                    {professional.name}
                                </Button>
                            ))}
                        </div>
                        <InputError message={errors.professional_id} />
                    </Step>
                )}

                {service && data.professional_id && (
                    <Step number={3} title="Data e horário">
                        <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2">
                            {dates.map((day) => (
                                <button
                                    key={day.value}
                                    type="button"
                                    onClick={() => selectDate(day.value)}
                                    aria-pressed={day.value === date}
                                    className={cn(
                                        'flex w-16 shrink-0 flex-col items-center rounded-lg border py-2 transition',
                                        day.value === date ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:border-primary/50',
                                    )}
                                >
                                    <span className="text-xs">{day.weekday}</span>
                                    <span className="text-lg font-semibold">{day.day}</span>
                                    <span className="text-xs">{day.month}</span>
                                </button>
                            ))}
                        </div>

                        {date &&
                            (loading ? (
                                <LoaderCircle className="text-muted-foreground size-5 animate-spin" />
                            ) : slots.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Nenhum horário livre neste dia. Tente outra data.</p>
                            ) : (
                                <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                    {slots.map((slot) => (
                                        <Button
                                            key={slot.starts_at}
                                            type="button"
                                            size="sm"
                                            variant={slot.starts_at === data.starts_at ? 'default' : 'outline'}
                                            onClick={() => setData('starts_at', slot.starts_at)}
                                        >
                                            {slot.time}
                                        </Button>
                                    ))}
                                </div>
                            ))}
                        <InputError message={errors.starts_at} />
                    </Step>
                )}

                {service && selectedSlot && (
                    <Step number={4} title="Seus dados">
                        <form onSubmit={submit} className="bg-background space-y-4 rounded-lg border p-4">
                            <p className="text-sm">
                                <span className="font-medium">{service.name}</span> às <span className="font-medium">{selectedSlot.time}</span> ·{' '}
                                {formatPrice(service.price_cents)}
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome</Label>
                                <Input id="name" required autoComplete="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        autoComplete="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Telefone (opcional)</Label>
                                    <Input id="phone" type="tel" autoComplete="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                    <InputError message={errors.phone} />
                                </div>
                            </div>

                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing && <LoaderCircle className="size-4 animate-spin" />}
                                Confirmar agendamento
                            </Button>
                        </form>
                    </Step>
                )}
            </div>
        </TenantLayout>
    );
}
