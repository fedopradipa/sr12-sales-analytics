@props([
    'messages',
    'id' => null,
])

@if ($messages)
    <div
        @if ($id) id="{{ $id }}" @endif
        class="mt-1 text-sm text-red-600"
        role="alert"
        aria-live="polite"
        data-form-error
    >
        @foreach ((array) $messages as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif