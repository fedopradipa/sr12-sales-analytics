@props([
    'id',
    'title',
    'maxWidth' => 'max-w-lg',
])

<dialog
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-title"
    {{ $attributes->merge([
        'class' => "m-auto w-[calc(100%_-_2rem)] {$maxWidth} rounded-2xl bg-transparent p-0 shadow-[0_35px_100px_rgba(77,36,43,.34)] backdrop:bg-[#382b31]/55 backdrop:backdrop-blur-sm",
    ]) }}
>
    <div class="overflow-hidden rounded-2xl border border-rose-200 bg-white">
        <header class="flex items-center border-b border-rose-100 bg-gradient-to-r from-rose-50 to-white px-6 py-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[.14em] text-blue-600">Form Data</p>
                <h2
                    id="{{ $id }}-title"
                    class="mt-1 text-lg font-semibold text-gray-900"
                    data-modal-title
                >
                    {{ $title }}
                </h2>
            </div>

        </header>

        <div class="p-6">
            {{ $slot }}
        </div>
    </div>
</dialog>
