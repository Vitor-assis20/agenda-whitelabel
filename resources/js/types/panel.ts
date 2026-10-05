export type TenantRole = 'owner' | 'admin' | 'staff';

export type AppointmentStatus = 'pending' | 'confirmed' | 'cancelled' | 'completed' | 'no_show';

export interface Panel {
    tenants: { id: number; name: string }[];
    current: {
        id: number;
        name: string;
        role: TenantRole;
        public_url: string;
    };
}

export interface ManagedService {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price_cents: number;
    is_active: boolean;
    professionals_count: number;
    deletable: boolean;
}

export type WorkingHour = {
    weekday: number;
    /** HH:mm, no fuso do negócio. */
    starts_at: string;
    ends_at: string;
};

export interface ManagedProfessional {
    id: number;
    name: string;
    email: string | null;
    bio: string | null;
    is_active: boolean;
    service_ids: number[];
    working_hours: WorkingHour[];
    deletable: boolean;
}

export interface ServiceOption {
    id: number;
    name: string;
    is_active: boolean;
}

export interface AgendaDate {
    value: string;
    label: string;
    previous: string;
    next: string;
    is_today: boolean;
}

export interface AgendaSummary {
    appointments: number;
    completed: number;
    expected_revenue_cents: number;
}

export interface AgendaAppointment {
    id: number;
    /** Horários locais do negócio (HH:mm). */
    starts_at: string;
    ends_at: string;
    service: string;
    customer: { name: string; email: string | null; phone: string | null };
    notes: string | null;
    price_cents: number;
    status: AppointmentStatus;
    /** Status para os quais o usuário pode mover o agendamento agora. */
    actions: AppointmentStatus[];
}

export interface AgendaProfessional {
    id: number;
    name: string;
    appointments: AgendaAppointment[];
}
