@php
    $column = $column_sorts[$key] ?? null;
@endphp
<th
    @class([
        'status-hide-sm' => ($hide ?? null) === 'sm',
        'status-hide-md' => ($hide ?? null) === 'md',
        'status-hide-lg' => ($hide ?? null) === 'lg',
    ])
    @if($column) aria-sort="{{ $column['aria_sort'] }}" @endif
>
    @if($column)
        <a
            href="{{ $column['url'] }}"
            @class(['status-sort', 'is-active' => $column['active']])
        >
            <span>{{ $column['label'] }}</span>
            <span class="status-sort__marks" aria-hidden="true">
                <span @class(['status-sort__mark', 'is-on' => $column['aria_sort'] === 'ascending'])></span>
                <span @class(['status-sort__mark', 'is-on' => $column['aria_sort'] === 'descending'])></span>
            </span>
            <span class="sr-only">
                @if($column['aria_sort'] === 'ascending')
                    Ordinato dal più basso. Attiva per il più alto.
                @elseif($column['aria_sort'] === 'descending')
                    Ordinato dal più alto. Attiva per il più basso.
                @else
                    Ordina per valore numerico.
                @endif
            </span>
        </a>
    @else
        {{ $label ?? '' }}
    @endif
</th>
