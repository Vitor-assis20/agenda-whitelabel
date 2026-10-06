{{-- Layout próprio: o padrão do Laravel exibe o nome da plataforma no cabeçalho e no rodapé. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$summary->bookingUrl">
{{ $summary->tenantName }}
</x-mail::header>
</x-slot:header>

# {{ $title }}

Olá, {{ $summary->customerFirstName() }}! {{ $intro }}

<x-mail::table>
| | |
| :--- | :--- |
| Serviço | {{ $summary->service }} |
| Profissional | {{ $summary->professional }} |
| Data | {{ $summary->date }} |
| Horário | {{ $summary->time }} |
| Valor | R$ {{ number_format($summary->priceCents / 100, 2, ',', '.') }} |
</x-mail::table>

@if ($actionUrl)
<x-mail::button :url="$actionUrl" :brand="$summary->brandColor">
{{ $actionText }}
</x-mail::button>
@endif

@if ($outro)
{{ $outro }}
@endif

<x-slot:footer>
<x-mail::footer>
{{ $summary->tenantName }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
