@foreach($filters as $filter)
    <a
        href="{{ $filter['url'] }}"
        @class(['status-chip', 'is-active' => $filter['active']])
        @if($filter['active']) aria-current="page" @endif
    >
        <span>{{ $filter['label'] }}</span>
        <span class="status-chip__count">{{ $filter['count'] }}</span>
    </a>
@endforeach
