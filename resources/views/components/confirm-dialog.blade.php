<div class="pointer-events-none fixed inset-0 z-[70] hidden items-center justify-center p-4"
     data-dialog role="dialog" aria-modal="true" aria-labelledby="dialog-title">
    <div class="absolute inset-0 bg-canvas/75 backdrop-blur-sm" data-dialog-cancel></div>

    <div class="surface-plain dialog-panel pointer-events-auto relative w-full max-w-md p-0">
        <div class="flex flex-col items-center gap-4 px-7 pt-8 pb-7 text-center sm:px-8">
            <span class="grid size-12 place-items-center rounded-full bg-danger/12 text-danger-text ring-1 ring-danger/25">
                <x-icon name="alert" class="size-6"/>
            </span>

            <div>
                <h2 id="dialog-title" class="text-base font-semibold text-ink">{{ __('app.dialog.title') }}</h2>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted" data-dialog-message></p>
            </div>
        </div>

        <div class="flex gap-2 border-t border-line px-5 py-4 sm:px-6">
            <button type="button" class="btn btn-ghost flex-1" data-dialog-cancel>
                {{ __('app.dialog.cancel') }}
            </button>
            <button type="button" class="btn btn-danger flex-1" data-dialog-accept>
                <x-icon name="check" class="size-4"/>
                {{ __('app.dialog.accept') }}
            </button>
        </div>
    </div>
</div>
