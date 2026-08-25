<x-app-layout :title="__('app.nav.settings')">
    <div class="stack-grid grid lg:grid-cols-2">
        <div class="stack">
            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-work/10 text-work-text"><x-icon name="clock" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.settings.worktime_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.worktime_hint') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.worktime') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="weekly_hours" class="label">{{ __('app.settings.weekly_hours') }}</label>
                            <input id="weekly_hours" type="text" inputmode="decimal" name="weekly_hours"
                                   value="{{ old('weekly_hours', rtrim(rtrim(number_format($user->weekly_hours, 2, ',', ''), '0'), ',')) }}"
                                   class="control metric" required>
                            @error('weekly_hours') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="working_days" class="label">{{ __('app.settings.working_days') }}</label>
                            <input id="working_days" type="number" min="1" max="7" step="1" name="working_days"
                                   value="{{ old('working_days', $user->working_days) }}" class="control metric" required>
                            @error('working_days') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="holiday_region" class="label">{{ __('app.settings.region') }}</label>
                            <select id="holiday_region" name="holiday_region" class="control">
                                @foreach ($regions as $code => $regionLabel)
                                    <option value="{{ $code }}" @selected(old('holiday_region', $user->holiday_region) === $code)>{{ $regionLabel }}</option>
                                @endforeach
                            </select>
                            @error('holiday_region') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="vacation_days" class="label">{{ __('app.settings.vacation_days') }}</label>
                            <input id="vacation_days" type="text" inputmode="decimal" name="vacation_days"
                                   value="{{ old('vacation_days', rtrim(rtrim(number_format($user->vacation_days, 1, ',', ''), '0'), ',')) }}"
                                   class="control metric" required>
                            @error('vacation_days') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="tile flex items-center justify-between gap-3 px-4 py-3">
                        <span class="text-xs text-faint">{{ __('app.settings.daily_target') }}</span>
                        <span class="metric text-lg font-bold text-work-text">{{ \App\Support\Duration::human($user->dailyTargetSeconds()) }}</span>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.settings.save') }}
                    </button>
                </form>
            </x-card>

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text"><x-icon name="user" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.settings.profile_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.profile_hint') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.profile') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="name" class="label">{{ __('app.auth.name') }}</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="control" required maxlength="120">
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="label">{{ __('app.auth.email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="control" required>
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="locale" class="label">{{ __('app.settings.language') }}</label>
                        <select id="locale" name="locale" class="control">
                            @foreach (['de' => 'Deutsch', 'en' => 'English'] as $code => $languageLabel)
                                <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $languageLabel }}</option>
                            @endforeach
                        </select>
                        @error('locale') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.settings.save') }}
                    </button>
                </form>
            </x-card>

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-work/10 text-work-text"><x-icon name="alert" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.settings.notify_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.notify_hint') }}</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <p class="text-xs text-muted" data-notify-status
                       data-state-default="{{ __('app.settings.notify_state_default') }}"
                       data-state-granted="{{ __('app.settings.notify_state_granted') }}"
                       data-state-denied="{{ __('app.settings.notify_state_denied') }}"
                       data-state-unsupported="{{ __('app.settings.notify_state_unsupported') }}"></p>

                    <button type="button" data-notify-request class="btn btn-ghost w-full text-xs">
                        <x-icon name="alert" class="size-3.5"/>
                        {{ __('app.settings.notify_action') }}
                    </button>

                    <form method="POST" action="{{ route('settings.notifications') }}" class="border-t border-line pt-3" data-live>
                        @csrf
                        @method('PUT')

                        <label class="flex cursor-pointer items-start gap-2.5">
                            <input type="checkbox" name="notify_worktime" value="1" data-autosave
                                   @checked($user->notify_worktime)
                                   class="mt-0.5 size-4 shrink-0 rounded-[4px] border-line-strong bg-raised text-accent">
                            <span>
                                <span class="block text-xs font-semibold text-ink">{{ __('app.notify.worktime') }}</span>
                                <span class="block text-[11px] leading-relaxed text-faint">{{ __('app.notify.worktime_hint') }}</span>
                            </span>
                        </label>
                    </form>

                    <p class="text-xs leading-relaxed text-faint">{{ __('app.settings.notify_limit') }}</p>
                </div>
            </x-card>

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text"><x-icon name="calendar-days" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.settings.ical_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.ical_hint') }}</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <input type="text" readonly value="{{ route('calendar.feed', ['token' => $user->icalToken()]) }}"
                           class="control metric text-xs" onclick="this.select()"
                           aria-label="{{ __('app.settings.ical_title') }}">

                    <form method="POST" action="{{ route('settings.ical') }}" data-confirm="{{ __('app.settings.confirm_ical') }}">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-ghost w-full text-xs">
                            <x-icon name="repeat" class="size-3.5"/>
                            {{ __('app.settings.regenerate_ical') }}
                        </button>
                    </form>
                </div>
            </x-card>

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-rest/10 text-rest-text"><x-icon name="lock" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.settings.password_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.password_hint') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.password') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="label">{{ __('app.settings.current_password') }}</label>
                        <input id="current_password" type="password" name="current_password" class="control" required autocomplete="current-password">
                        @error('current_password') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="new_password" class="label">{{ __('app.settings.new_password') }}</label>
                            <input id="new_password" type="password" name="password" class="control" required autocomplete="new-password">
                            @error('password') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="label">{{ __('app.auth.password_confirm') }}</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="control" required autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        <x-icon name="lock" class="size-4"/>
                        {{ __('app.settings.change_password') }}
                    </button>
                </form>
            </x-card>
        </div>

        <div class="stack">
            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text"><x-icon name="swatch" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.settings.theme_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.theme_hint') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ($themes as $option)
                        @php
                            $preview = $option->preview();
                            $active = $user->theme === $option;
                        @endphp

                        <form method="POST" action="{{ route('settings.theme') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="theme" value="{{ $option->value }}">

                            <button type="submit"
                                    @class([
                                        'block w-full overflow-hidden rounded-[var(--radius-control)] border text-left transition',
                                        'border-accent ring-2 ring-accent/30' => $active,
                                        'border-line hover:border-line-strong' => ! $active,
                                    ])>
                                <span class="block h-16 p-2" @style([
                                        'background: '.$preview['canvas'] => ! $option->isAutomatic(),
                                        'background: linear-gradient(135deg, #060911 50%, #f4f6fb 50%)' => $option->isAutomatic(),
                                    ])>
                                    <span class="flex h-full flex-col justify-between rounded p-1.5" @style([
                                            'background: '.$preview['surface'] => ! $option->isAutomatic(),
                                            'background: linear-gradient(135deg, #161c2c 50%, #ffffff 50%)' => $option->isAutomatic(),
                                        ])>
                                        <span class="flex items-center gap-1">
                                            <span class="size-1.5 rounded-full" style="background: {{ $preview['accent'] }}"></span>
                                            <span class="h-1 w-8 rounded-full" style="background: {{ $preview['ink'] }}; opacity: .7"></span>
                                        </span>
                                        <span class="flex items-end gap-1">
                                            <span class="h-3 w-2.5 rounded-sm" style="background: {{ $preview['accent'] }}"></span>
                                            <span class="h-5 w-2.5 rounded-sm" style="background: {{ $preview['accent'] }}; opacity: .55"></span>
                                        </span>
                                    </span>
                                </span>

                                <span class="flex items-center justify-between gap-2 border-t border-line bg-raised px-3 py-2">
                                    <span>
                                        <span class="block text-xs font-semibold text-ink">{{ $option->label() }}</span>
                                        <span class="block text-[10px] text-faint">{{ $option->description() }}</span>
                                    </span>
                                    @if ($active)<span class="shrink-0 text-accent-text"><x-icon name="check" class="size-4"/></span>@endif
                                </span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </x-card>

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-work/10 text-work-text"><x-icon name="list-check" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.settings.style_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.style_hint') }}</p>
                    </div>
                </div>

                @php
                    $styles = \App\Enums\DesignStyle::cases();
                    $isActiveStyle = $user->design_style === $previewedStyle;
                @endphp

                <div class="mt-5" data-style-carousel data-active-style="{{ $user->design_style->value }}">
                    <div class="flex items-stretch gap-2">
                        <a href="{{ route('settings', ['stil' => $previousStyle->value]) }}"
                           class="btn btn-icon shrink-0 self-stretch px-2" data-style-step="-1"
                           aria-label="{{ __('app.settings.style_previous') }}">
                            <x-icon name="chevron-left" class="size-4"/>
                        </a>

                        <div class="min-w-0 flex-1">
                            @foreach ($styles as $option)
                                <div data-style-slide="{{ $option->value }}"
                                     data-style-label="{{ $option->label() }}"
                                     data-style-description="{{ $option->description() }}"
                                     data-style-position="{{ $loop->iteration }}"
                                     @class(['hidden' => $option !== $previewedStyle])>
                                    <x-style-preview :style="$option"/>
                                </div>
                            @endforeach
                        </div>

                        <a href="{{ route('settings', ['stil' => $nextStyle->value]) }}"
                           class="btn btn-icon shrink-0 self-stretch px-2" data-style-step="1"
                           aria-label="{{ __('app.settings.style_next') }}">
                            <x-icon name="chevron-right" class="size-4"/>
                        </a>
                    </div>

                    <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink" data-style-name>{{ $previewedStyle->label() }}</p>
                            <p class="mt-0.5 text-xs leading-snug text-faint" data-style-text>{{ $previewedStyle->description() }}</p>
                        </div>
                        <span class="metric shrink-0 text-xs text-dim">
                            <span data-style-index>{{ $stylePosition }}</span> / {{ $styleCount }}
                        </span>
                    </div>

                    <p class="btn btn-ghost mt-4 w-full cursor-default text-work-text @unless ($isActiveStyle) hidden @endunless"
                       data-style-active>
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.settings.style_active') }}
                    </p>

                    <form method="POST" action="{{ route('settings.style') }}"
                          class="mt-4 @if ($isActiveStyle) hidden @endif" data-style-form>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="design_style" value="{{ $previewedStyle->value }}">
                        <button type="submit" class="btn btn-primary w-full">
                            <x-icon name="check" class="size-4"/>
                            {{ __('app.settings.style_choose') }}
                        </button>
                    </form>
                </div>
            </x-card>

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-work/10 text-work-text"><x-icon name="download" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.backup.title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.backup.hint') }}</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <a href="{{ route('backup') }}" class="btn btn-ghost w-full text-xs">
                        <x-icon name="download" class="size-3.5"/>
                        {{ __('app.month.backup_action') }}
                    </a>

                    <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data" class="space-y-2 border-t border-line pt-3">
                        @csrf

                        <label for="backup" class="label">{{ __('app.backup.restore_title') }}</label>
                        <input type="file" name="backup" id="backup" accept="application/json,.json"
                               class="control text-xs file:mr-3 file:rounded-[var(--radius-pill)] file:border-0 file:bg-hover file:px-3 file:py-1 file:text-xs file:text-ink">
                        <p class="text-[11px] text-dim">{{ __('app.backup.restore_hint') }}</p>
                        @error('backup') <p class="field-error">{{ $message }}</p> @enderror

                        <button type="submit" class="btn btn-ghost w-full text-xs" data-confirm="{{ __('app.backup.confirm') }}">
                            <x-icon name="repeat" class="size-3.5"/>
                            {{ __('app.backup.restore_action') }}
                        </button>
                    </form>
                </div>
            </x-card>

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-info/10 text-info-text"><x-icon name="gear" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.backup.settings_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.backup.settings_hint') }}</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <a href="{{ route('settings.export') }}" class="btn btn-ghost w-full text-xs">
                        <x-icon name="download" class="size-3.5"/>
                        {{ __('app.backup.settings_export') }}
                    </a>

                    <form method="POST" action="{{ route('settings.import') }}" enctype="multipart/form-data" class="space-y-2 border-t border-line pt-3">
                        @csrf

                        <label for="settings" class="label">{{ __('app.backup.settings_import') }}</label>
                        <input type="file" name="settings" id="settings" accept="application/json,.json"
                               class="control text-xs file:mr-3 file:rounded-[var(--radius-pill)] file:border-0 file:bg-hover file:px-3 file:py-1 file:text-xs file:text-ink">
                        <p class="text-[11px] text-dim">{{ __('app.backup.settings_import_hint') }}</p>
                        @error('settings') <p class="field-error">{{ $message }}</p> @enderror

                        <button type="submit" class="btn btn-ghost w-full text-xs" data-confirm="{{ __('app.backup.settings_confirm') }}">
                            <x-icon name="repeat" class="size-3.5"/>
                            {{ __('app.backup.settings_import_action') }}
                        </button>
                    </form>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
