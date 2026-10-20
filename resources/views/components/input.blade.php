

@props([
    'disabled' => false,
    'type' => 'text',
])

<input
    type="{{ $type }}"
    @disabled($disabled)
    {{ $attributes->merge([
        'class' => 'block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50'
    ]) }}
>