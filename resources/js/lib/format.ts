const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export function formatPrice(cents: number): string {
    return currency.format(cents / 100);
}

export function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) return `${rest} min`;

    return rest === 0 ? `${hours}h` : `${hours}h${String(rest).padStart(2, '0')}`;
}
