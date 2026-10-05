import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Trash2, TriangleAlert } from 'lucide-react';
import { type FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type ManagedTimeOff } from '@/types/panel';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Folgas', href: '/painel/folgas' }];

type TimeOffForm = {
    professional_id: string;
    all_day: boolean;
    start_date: string;
    end_date: string;
    start_time: string;
    end_time: string;
    reason: string;
    confirm_conflicts: boolean;
};

interface TimeOffsProps {
    time_offs: ManagedTimeOff[];
    professionals: { id: number; name: string }[];
}

export default function TimeOffs({ time_offs, professionals }: TimeOffsProps) {
    const { data, setData, transform, post, processing, errors, reset, clearErrors } = useForm<TimeOffForm>({
        professional_id: professionals.length === 1 ? String(professionals[0].id) : '',
        all_day: true,
        start_date: '',
        end_date: '',
        start_time: '09:00',
        end_time: '12:00',
        reason: '',
        confirm_conflicts: false,
    });
    const fieldErrors = errors as Record<string, string>;

    const save = (confirmConflicts: boolean) => {
        transform((form) => ({ ...form, professional_id: Number(form.professional_id), confirm_conflicts: confirmConflicts }));
        post(route('panel.time-offs.store'), {
            preserveScroll: true,
            onSuccess: () => reset('start_date', 'end_date', 'reason'),
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        save(false);
    };

    // Mudou o formulário: o aviso de conflito antigo não vale mais.
    const change = <K extends keyof TimeOffForm>(field: K, value: TimeOffForm[K]) => {
        setData(field, value);
        clearErrors('conflicts' as keyof TimeOffForm);
    };

    const destroy = (timeOff: ManagedTimeOff) => {
        if (window.confirm(`Remover a folga de ${timeOff.professional} (${timeOff.period})?`)) {
            router.delete(route('panel.time-offs.destroy', { timeOff: timeOff.id }), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Folgas" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Folgas e bloqueios</h1>
                    <p className="text-muted-foreground text-sm">Períodos em que o profissional não recebe agendamentos.</p>
                </div>

                <form onSubmit={submit} className="space-y-4 rounded-xl border p-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Profissional</Label>
                            <Select value={data.professional_id} onValueChange={(value) => change('professional_id', value)}>
                                <SelectTrigger aria-label="Profissional">
                                    <SelectValue placeholder="Escolha" />
                                </SelectTrigger>
                                <SelectContent>
                                    {professionals.map((professional) => (
                                        <SelectItem key={professional.id} value={String(professional.id)}>
                                            {professional.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.professional_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Duração</Label>
                            <div className="flex gap-2">
                                {[
                                    { value: true, label: 'Dia inteiro' },
                                    { value: false, label: 'Parte do dia' },
                                ].map((option) => (
                                    <Button
                                        key={option.label}
                                        type="button"
                                        variant={data.all_day === option.value ? 'default' : 'outline'}
                                        onClick={() => change('all_day', option.value)}
                                    >
                                        {option.label}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="start_date">{data.all_day ? 'De' : 'Data'}</Label>
                            <Input
                                id="start_date"
                                type="date"
                                required
                                value={data.start_date}
                                onChange={(e) => change('start_date', e.target.value)}
                            />
                            <InputError message={errors.start_date} />
                        </div>

                        {data.all_day ? (
                            <div className="grid gap-2">
                                <Label htmlFor="end_date">Até</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    required
                                    min={data.start_date}
                                    value={data.end_date}
                                    onChange={(e) => change('end_date', e.target.value)}
                                />
                                <InputError message={errors.end_date} />
                            </div>
                        ) : (
                            <div className="grid gap-2">
                                <Label>Horário</Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        type="time"
                                        aria-label="Início"
                                        required
                                        value={data.start_time}
                                        onChange={(e) => change('start_time', e.target.value)}
                                    />
                                    <span className="text-muted-foreground">até</span>
                                    <Input
                                        type="time"
                                        aria-label="Fim"
                                        required
                                        value={data.end_time}
                                        onChange={(e) => change('end_time', e.target.value)}
                                    />
                                </div>
                                <InputError message={errors.start_time ?? errors.end_time} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="reason">Motivo (opcional)</Label>
                            <Input
                                id="reason"
                                placeholder="Férias, curso, consulta…"
                                value={data.reason}
                                onChange={(e) => change('reason', e.target.value)}
                            />
                            <InputError message={errors.reason} />
                        </div>
                    </div>

                    {fieldErrors.conflicts ? (
                        <div className="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 sm:flex-row sm:items-center dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <TriangleAlert className="size-5 shrink-0" />
                            <p className="flex-1">{fieldErrors.conflicts}</p>
                            <Button type="button" variant="outline" disabled={processing} onClick={() => save(true)}>
                                Registrar mesmo assim
                            </Button>
                        </div>
                    ) : (
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            Registrar folga
                        </Button>
                    )}
                </form>

                <section className="space-y-2">
                    <h2 className="font-semibold">Próximas folgas</h2>
                    {time_offs.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Nenhuma folga programada.</p>
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {time_offs.map((timeOff) => (
                                <li key={timeOff.id} className="flex items-center gap-4 p-4">
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium">{timeOff.professional}</div>
                                        <div className="text-muted-foreground text-sm">
                                            {timeOff.period}
                                            {timeOff.reason && ` · ${timeOff.reason}`}
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="icon" aria-label="Remover" onClick={() => destroy(timeOff)}>
                                        <Trash2 />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
