@php
    $statusClasses = match ($status) {
        'operational' => 'bg-emerald-100 text-emerald-700',
        'maintenance' => 'bg-amber-100 text-amber-700',
        'down' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<span @class(['rounded-full px-3 py-1 text-sm font-medium', $statusClasses])>{{ $label }}</span>
