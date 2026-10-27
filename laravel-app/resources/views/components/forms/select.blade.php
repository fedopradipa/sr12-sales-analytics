@props([
    'label',
    'name',
])

<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-semibold text-gray-700">
        {{ $label }}
    </label>

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-xl border border-rose-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100'
        ]) }}
    >
        {{ $slot }}
    </select>

    <x-forms.input-error :messages="$errors->get($name)" />
</div>
