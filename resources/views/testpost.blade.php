<x-app-layout :title="__('app.dev.testpost')">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('app.dev.testpost') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.dev.testpost_hint') }}</p>
            </div>

            <x-dev-tabs active="dev.testpost"/>
        </div>
    </x-card>

    <div class="stack-grid mt-5 grid lg:grid-cols-2">
        <x-card class="rise">
            <h2 class="heading">{{ __('app.dev.fields') }}</h2>

            <form method="GET" action="{{ route('dev.testpost') }}" class="mt-4 space-y-3">
                <div>
                    <label for="ticket" class="label">{{ __('app.dev.ticket') }}</label>
                    <input id="ticket" type="text" name="ticket" value="{{ $input['ticket'] ?? '' }}"
                           class="control metric text-xs" maxlength="400" placeholder="COR-6944" autofocus>
                </div>

                <div>
                    <label for="pr" class="label">{{ __('app.dev.pr') }}</label>
                    <input id="pr" type="text" name="pr" value="{{ $input['pr'] ?? '' }}"
                           class="control metric text-xs" maxlength="400" placeholder="2456">
                </div>

                <div>
                    <label for="instance" class="label">{{ __('app.dev.instance') }}</label>
                    <input id="instance" type="text" name="instance" value="{{ $input['instance'] ?? '' }}"
                           class="control metric text-xs" maxlength="400" placeholder="b63d4865/mod/zeiterfassung/?fn=time_list">
                    <p class="mt-1 text-[11px] text-dim">{{ __('app.dev.instance_hint') }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary text-xs">
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.dev.build') }}
                    </button>
                    <a href="{{ route('dev.testpost') }}" class="btn btn-ghost text-xs">{{ __('app.form.reset') }}</a>
                </div>
            </form>

            <div class="mt-5 space-y-1.5 border-t border-line pt-4 text-[11px] text-faint">
                @unless ($slackReady)
                    <p>{{ __('app.slack.setup_note') }}
                        <a href="{{ route('settings') }}" class="text-accent-text hover:underline">{{ __('app.nav.settings') }}</a>
                    </p>
                @endunless

                <p>{{ __('app.dev.templates_note') }}</p>
                <p class="metric break-all">{{ $defaults['ticket'] }}</p>
                <p class="metric break-all">{{ $defaults['pr'] }}</p>
                <p class="metric break-all">{{ $defaults['instance'] }}</p>
            </div>
        </x-card>

        <x-card class="rise self-start">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="heading">{{ __('app.dev.preview') }}</h2>

                @if ($result['missing'] === [])
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn btn-ghost text-xs" data-copy="{{ $result['text'] }}"
                                data-copy-label="{{ __('app.dev.copied') }}">
                            <x-icon name="paperclip" class="size-4"/>
                            {{ __('app.dev.copy_post') }}
                        </button>

                        @if ($slackReady)
                            <form method="POST" action="{{ route('dev.testpost.send') }}"
                                  data-confirm="{{ __('app.slack.confirm') }}" data-live>
                                @csrf
                                <input type="hidden" name="ticket" value="{{ $input['ticket'] ?? '' }}">
                                <input type="hidden" name="pr" value="{{ $input['pr'] ?? '' }}">
                                <input type="hidden" name="instance" value="{{ $input['instance'] ?? '' }}">

                                <button type="submit" class="btn btn-primary text-xs">
                                    <x-icon name="send" class="size-4"/>
                                    {{ __('app.slack.send') }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            @error('slack')
                <p class="mt-4 rounded-[var(--radius-control)] border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger-text">
                    {{ $message }}
                </p>
            @enderror

            @if (session('slack_permalink'))
                <a href="{{ session('slack_permalink') }}" target="_blank"
                   class="row mt-4 flex items-center gap-2 px-3 py-2 text-xs text-accent-text hover:text-ink">
                    <x-icon name="external" class="size-3.5 shrink-0"/>
                    {{ __('app.slack.open_message') }}
                </a>
            @endif

            <pre class="metric mt-4 whitespace-pre-wrap break-all rounded-[var(--radius-control)] border border-line bg-panel p-4 text-xs leading-relaxed text-ink">{{ $result['text'] }}</pre>

            @if ($result['missing'] !== [])
                <p class="mt-3 rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                    {{ __('app.dev.missing_fields', ['fields' => collect($result['missing'])->map(fn ($key) => __('app.dev.'.$key))->implode(', ')]) }}
                </p>
            @endif

            <div class="mt-4 space-y-2 border-t border-line pt-4">
                @foreach ([['ticket', $result['ticket']], ['pr', $result['pr']], ['instance', $result['instance']]] as [$key, $url])
                    @if ($url !== '')
                        <a href="{{ $url }}" target="_blank" class="row flex items-center gap-3 px-3 py-2 text-xs hover:text-ink">
                            <span class="pill shrink-0 text-[10px]">{{ __('app.dev.'.$key) }}</span>
                            <span class="metric min-w-0 flex-1 truncate text-muted">{{ $url }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </x-card>
    </div>
</x-app-layout>
