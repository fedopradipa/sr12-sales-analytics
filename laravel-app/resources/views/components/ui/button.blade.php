@props([
    'type' => 'submit',
    'variant' => 'primary',
    'size' => 'medium',
    'full' => false,
    'href' => null,
])

@php
    $baseClass = implode(' ', [
        'inline-flex',
        'items-center',
        'justify-center',
        'gap-2',
        'rounded-xl',
        'font-semibold',
        'leading-none',
        'transition',
        'duration-150',
        'focus:outline-none',
        'focus:ring-2',
        'focus:ring-offset-2',
        'disabled:cursor-not-allowed',
        'disabled:opacity-55',
    ]);

    $sizeClass = match ($size) {
        'small' => 'min-h-8 px-3 py-2 text-xs',
        'large' => 'min-h-12 px-5 py-3 text-sm',
        default => 'min-h-10 px-4 py-2.5 text-sm',
    };

    $variantClass = match ($variant) {
        'danger' => implode(' ', [
            'bg-red-600',
            'text-white',
            'shadow-sm',
            'hover:bg-red-700',
            'focus:ring-red-300',
        ]),

        'success' => implode(' ', [
            'bg-green-600',
            'text-white',
            'shadow-sm',
            'hover:bg-green-700',
            'focus:ring-green-300',
        ]),

        'secondary' => implode(' ', [
            'border',
            'border-rose-200',
            'bg-white',
            'text-gray-700',
            'shadow-sm',
            'hover:bg-rose-50',
            'focus:ring-rose-200',
        ]),

        'ghost' => implode(' ', [
            'border',
            'border-transparent',
            'bg-transparent',
            'text-blue-700',
            'hover:bg-blue-50',
            'focus:ring-blue-200',
        ]),

        default => implode(' ', [
            'bg-blue-600',
            'text-white',
            'shadow-[0_10px_24px_rgba(183,111,117,.24)]',
            'hover:bg-blue-700',
            'focus:ring-blue-300',
        ]),
    };

    $widthClass = $full ? 'w-full' : '';

    $classes = trim(
        "{$baseClass} {$sizeClass} "
        . "{$variantClass} {$widthClass}"
    );
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        {{ $attributes->merge([
            'class' => $classes,
        ]) }}
    >
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->merge([
            'class' => $classes,
        ]) }}
    >
        {{ $slot }}
    </button>
@endif
