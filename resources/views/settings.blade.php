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

                    <div>
                        <label for="home_office_days" class="label">{{ __('app.settings.home_office_days') }}</label>
                        <input id="home_office_days" type="number" name="home_office_days" min="0" max="7" step="1"
                               value="{{ old('home_office_days', $user->home_office_days) }}" class="control metric">
                        <p class="mt-1 text-[11px] text-faint">{{ __('app.settings.home_office_hint') }}</p>
                        @error('home_office_days') <p class="field-error">{{ $message }}</p> @enderror
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

                <x-choice-carousel
                    param="farbe"
                    field="theme"
                    :action="route('settings.theme')"
                    :previewed="$previewedTheme"
                    :previous="$previousTheme"
                    :next="$nextTheme"
                    :position="$themePosition"
                    :count="$themeCount"
                    :active="$user->theme"
                    :choose-label="__('app.settings.theme_choose')"
                    :active-label="__('app.settings.theme_active')"
                    :previous-label="__('app.settings.theme_previous')"
                    :next-label="__('app.settings.theme_next')">
                    @foreach ($themes as $option)
                        <x-choice-slide :case="$option" :position="$loop->iteration" :shown="$option === $previewedTheme">
                            <x-theme-preview :theme="$option"/>
                        </x-choice-slide>
                    @endforeach
                </x-choice-carousel>
            </x-card>

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-work/10 text-work-text"><x-icon name="list-check" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.settings.style_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.style_hint') }}</p>
                    </div>
                </div>

                <x-choice-carousel
                    param="stil"
                    field="design_style"
                    :action="route('settings.style')"
                    :previewed="$previewedStyle"
                    :previous="$previousStyle"
                    :next="$nextStyle"
                    :position="$stylePosition"
                    :count="$styleCount"
                    :active="$user->design_style"
                    :choose-label="__('app.settings.style_choose')"
                    :active-label="__('app.settings.style_active')"
                    :previous-label="__('app.settings.style_previous')"
                    :next-label="__('app.settings.style_next')">
                    @foreach ($styles as $option)
                        <x-choice-slide :case="$option" :position="$loop->iteration" :shown="$option === $previewedStyle">
                            <x-style-preview :style="$option"/>
                        </x-choice-slide>
                    @endforeach
                </x-choice-carousel>
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

            <x-card class="rise">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-info/10 text-info-text"><x-icon name="terminal" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.nav.dev') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.settings.dev_hint') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.developer') }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PUT')

                    <div class="rounded-[var(--radius-control)] border border-line bg-raised p-3.5">
                        <form method="POST" action="{{ route('trail.update') }}" class="space-y-3" data-live>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="activity_trail" value="{{ $user->activity_trail ? 0 : 1 }}">

                            <span class="flex items-center justify-between gap-3">
                                <span class="label mb-0">{{ __('app.trail.title') }}</span>
                                <button type="submit" @class(['btn text-xs', 'btn-work' => ! $user->activity_trail, 'btn-ghost' => $user->activity_trail])>
                                    {{ $user->activity_trail ? __('app.form.off') : __('app.form.on') }}
                                </button>
                            </span>

                            <p class="text-[11px] leading-relaxed text-dim">{{ __('app.trail.hint') }}</p>
                            <p class="text-[11px] leading-relaxed text-faint">{{ __('app.trail.needs_permission') }}</p>

                            @if ($user->activity_trail)
                                <p class="text-[11px] leading-relaxed text-faint">{{ __('app.trail.off_note') }}</p>
                            @endif
                        </form>

                        @if ($user->activity_trail)
                            <form method="POST" action="{{ route('trail.update') }}" class="mt-3 border-t border-line pt-3" data-live>
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="activity_trail" value="1">

                                <label for="activity_retention_days" class="label">{{ __('app.trail.retention') }}</label>
                                <span class="flex items-center gap-2">
                                    <input id="activity_retention_days" type="number" name="activity_retention_days" min="1" max="365"
                                           value="{{ $user->activity_retention_days }}" class="control metric text-xs">
                                    <button type="submit" class="btn btn-ghost shrink-0 text-xs">
                                        <x-icon name="check" class="size-3.5"/>
                                    </button>
                                </span>
                                @error('activity_retention_days') <p class="field-error">{{ $message }}</p> @enderror
                            </form>
                        @endif
                    </div>

                    <div class="rounded-[var(--radius-control)] border border-line bg-raised p-3.5">
                        <form method="POST" action="{{ route('settings.network') }}" data-live>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="enabled" value="{{ $networkEnabled ? 0 : 1 }}">

                            <span class="flex items-center justify-between gap-3">
                                <span class="label mb-0">{{ __('app.network.title') }}</span>
                                <button type="submit" @class(['btn text-xs', 'btn-work' => ! $networkEnabled, 'btn-ghost' => $networkEnabled])>
                                    {{ $networkEnabled ? __('app.form.off') : __('app.form.on') }}
                                </button>
                            </span>
                        </form>

                        <p class="mt-2 text-[11px] leading-relaxed text-dim">{{ __('app.network.hint') }}</p>

                        @if ($networkEnabled)
                            <div class="mt-3 flex items-center gap-2">
                                @if ($networkAddress !== null)
                                    <span class="metric min-w-0 flex-1 truncate text-xs text-ink">{{ $networkAddress }}</span>
                                    <button type="button" class="icon-action shrink-0" data-copy="{{ $networkAddress }}"
                                            data-copy-label="{{ __('app.network.copied') }}" title="{{ __('app.network.copy') }}">
                                        <x-icon name="clipboard" class="size-3.5"/>
                                    </button>
                                @else
                                    <span class="text-xs text-faint">{{ __('app.network.no_ip') }}</span>
                                @endif
                            </div>

                            <p class="mt-1.5 text-[11px] text-faint">{{ __('app.network.needs_restart') }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="linear_token" class="label">{{ __('app.linear.token') }}</label>
                        <input id="linear_token" type="password" name="linear_token" class="control metric text-xs"
                               autocomplete="off" placeholder="{{ $user->linear_token ? __('app.settings.token_set') : 'lin_api_…' }}">
                        <p class="mt-1 text-[11px] leading-relaxed text-dim">{{ __('app.linear.token_hint') }}</p>
                        @error('linear_token') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="flex items-center justify-between gap-2">
                            <label for="github_token" class="label">{{ __('app.settings.github_token') }}</label>

                            <x-hint :title="__('app.dev.github_guide_title')">
                                <ol>
                                    <li>{!! __('app.dev.github_guide_1') !!}</li>
                                    <li>{!! __('app.dev.github_guide_2') !!}</li>
                                    <li>{!! __('app.dev.github_guide_3') !!}</li>
                                    <li>{!! __('app.dev.github_guide_4') !!}</li>
                                </ol>

                                <span class="mt-2 block text-[11px] text-faint">{!! __('app.dev.github_guide_note') !!}</span>
                            </x-hint>
                        </span>
                        <input id="github_token" type="password" name="github_token" class="control metric text-xs"
                               autocomplete="off" placeholder="{{ $user->github_token ? __('app.settings.token_set') : 'ghp_…' }}">
                        <p class="mt-1 text-[11px] leading-relaxed text-dim">{{ __('app.settings.github_token_hint') }}</p>
                        @error('github_token') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="flex items-center justify-between gap-2">
                            <label for="slack_token" class="label">{{ __('app.settings.slack_token') }}</label>

                            <x-hint :title="__('app.slack.guide_title')">
                                <ol>
                                    <li>{!! __('app.slack.guide_1') !!}</li>
                                    <li>{!! __('app.slack.guide_2') !!}</li>
                                    <li>{!! __('app.slack.guide_3') !!}</li>
                                    <li>{!! __('app.slack.guide_4') !!}</li>
                                    <li>{!! __('app.slack.guide_5') !!}</li>
                                    <li>{!! __('app.slack.guide_6') !!}</li>
                                </ol>

                                <span class="mt-2 block text-[11px] text-faint">{!! __('app.slack.guide_note') !!}</span>
                            </x-hint>
                        </span>
                        <input id="slack_token" type="password" name="slack_token" class="control metric text-xs"
                               autocomplete="off" placeholder="{{ $user->slack_token ? __('app.settings.token_set') : 'xoxp-…' }}">
                        <p class="mt-1 text-[11px] leading-relaxed text-dim">{{ __('app.settings.slack_token_hint') }}</p>
                        @error('slack_token') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="slack_channel" class="label">{{ __('app.settings.slack_channel') }}</label>
                        <input id="slack_channel" type="text" name="slack_channel" class="control metric text-xs"
                               value="{{ old('slack_channel', $user->slack_channel) }}" maxlength="120" placeholder="#testing">
                        <p class="mt-1 text-[11px] leading-relaxed text-dim">{{ __('app.settings.slack_channel_hint') }}</p>
                    </div>

                    <div>
                        <label for="ticket_url_template" class="label">{{ __('app.settings.ticket_template') }}</label>
                        <input id="ticket_url_template" type="text" name="ticket_url_template" class="control metric text-xs"
                               value="{{ old('ticket_url_template', $user->ticket_url_template) }}"
                               placeholder="{{ \App\Services\TestPost::TICKET_DEFAULT }}">
                    </div>

                    <div>
                        <label for="pr_url_template" class="label">{{ __('app.settings.pr_template') }}</label>
                        <input id="pr_url_template" type="text" name="pr_url_template" class="control metric text-xs"
                               value="{{ old('pr_url_template', $user->pr_url_template) }}"
                               placeholder="{{ \App\Services\TestPost::PR_DEFAULT }}">
                    </div>

                    <div>
                        <label for="instance_url_template" class="label">{{ __('app.settings.instance_template') }}</label>
                        <input id="instance_url_template" type="text" name="instance_url_template" class="control metric text-xs"
                               value="{{ old('instance_url_template', $user->instance_url_template) }}"
                               placeholder="{{ \App\Services\TestPost::INSTANCE_DEFAULT }}">
                        <p class="mt-1 text-[11px] text-dim">{{ __('app.settings.template_hint') }}</p>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.settings.save') }}
                    </button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
