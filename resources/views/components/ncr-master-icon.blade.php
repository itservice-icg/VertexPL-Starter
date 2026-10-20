@props(['type'])

@php($iconClasses = [
    'problem-types' => 'ph-warning',
    'severity-levels' => 'ph-gauge',
    'disposition-types' => 'ph-arrows-left-right',
    'root-cause-types' => 'ph-magnifying-glass',
    'units' => 'ph-ruler',
    'products' => 'ph-cube',
    'customers' => 'ph-users-three',
    'suppliers' => 'ph-buildings',
    'notification-rules' => 'ph-bell',
])

<i {{ $attributes->merge(['class' => 'ph '.($iconClasses[$type] ?? 'ph-squares-four').' h-4 w-4 shrink-0 text-base leading-none', 'aria-hidden' => 'true']) }}></i>
