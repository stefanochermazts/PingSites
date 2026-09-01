<span @class([
    'status-badge',
    'status-badge--operational' => $status === 'operational',
    'status-badge--maintenance' => $status === 'maintenance',
    'status-badge--down' => $status === 'down',
    'status-badge--unknown' => ! in_array($status, ['operational', 'maintenance', 'down'], true),
])>{{ $label }}</span>
