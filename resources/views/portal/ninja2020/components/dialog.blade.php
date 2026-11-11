<dialog id="{{ $id }}" class="portal-dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" data-error-message="{{ ctrans('texts.an_error_occurred_try_again') }}">
    <div class="portal-dialog-body">
        <h2 id="{{ $id }}-title" class="text-xl font-medium mb-4">{{ $title }}</h2>
        {{ $slot }}
    </div>
    <div class="portal-dialog-actions">
        {{ $actions }}
    </div>
</dialog>
