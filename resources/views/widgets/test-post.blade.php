<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.dev.testpost') }}</h2>
        <a href="{{ route('dev.testpost') }}" class="pill hover:text-ink">{{ __('app.dev.build') }}</a>
    </div>

    <form method="GET" action="{{ route('dev.testpost') }}" class="mt-4 space-y-2">
        <input type="text" name="ticket" class="control metric text-xs" maxlength="400" placeholder="COR-6944">
        <input type="text" name="pr" class="control metric text-xs" maxlength="400" placeholder="2456">
        <input type="text" name="instance" class="control metric text-xs" maxlength="400" placeholder="b63d4865/mod/…">

        <button type="submit" class="btn btn-primary w-full text-xs">
            <x-icon name="check" class="size-4"/>
            {{ __('app.dev.build') }}
        </button>
    </form>
</x-card>
