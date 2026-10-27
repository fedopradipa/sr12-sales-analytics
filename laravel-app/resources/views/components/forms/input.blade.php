@props([
    'label',
    'name',
    'type' => 'text',
    'value' => '',
    'placeholder' => '',
    'autofocus' => false,
])

@php
    $errorMessages = $errors->get($name);
    $errorId = "{$name}-error";
@endphp

<div>
    <label
        for="{{ $name }}"
        class="mb-2 block text-sm font-semibold text-gray-700"
    >
        {{ $label }}
    </label>

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        @if ($autofocus) autofocus @endif
        @if ($errorMessages)
            aria-invalid="true"
            aria-describedby="{{ $errorId }}"
        @else
            aria-invalid="false"
        @endif
        {{ $attributes->merge([
            'class' => 'w-full rounded-xl border border-rose-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100'
        ]) }}
    >

    <x-forms.input-error
        :id="$errorId"
        :messages="$errorMessages"
    />
</div>
