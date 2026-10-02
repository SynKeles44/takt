<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Everything the dashboard can show. The order of the cases is the order of the
 * catalogue; what a dashboard actually shows is stored per user.
 */
enum Widget: string
{
    // time
    case Timer = 'timer';
    case Stats = 'stats';
    case WeekChart = 'week_chart';
    case Entries = 'entries';
    case Booking = 'booking';
    case Note = 'note';
    case MonthSummary = 'month_summary';
    case WeekBalance = 'week_balance';
    case YearHeatmap = 'year_heatmap';
    case Absences = 'absences';
    case HomeOffice = 'home_office';
    case Meetings = 'meetings';
    case Activity = 'activity';

    // tasks
    case Todos = 'todos';
    case TodoTags = 'todo_tags';
    case TodoProgress = 'todo_progress';

    // development
    case CommitsToday = 'commits_today';
    case CommitsWeek = 'commits_week';
    case ReviewQueue = 'review_queue';
    case MyPullRequests = 'my_pull_requests';
    case ApprovedPulls = 'approved_pulls';
    case ProjectLauncher = 'project_launcher';
    case Snippets = 'snippets';
    case TestPost = 'test_post';
    case DevLinks = 'dev_links';
    case Tickets = 'tickets';
    case Packages = 'packages';
    case Docker = 'docker';
    case Releases = 'releases';
    case Runs = 'runs';

    public function label(): string
    {
        return __('app.widget.'.$this->value.'.label');
    }

    public function description(): string
    {
        return __('app.widget.'.$this->value.'.description');
    }

    public function group(): WidgetGroup
    {
        return match ($this) {
            self::Timer, self::Stats, self::WeekChart, self::Entries, self::Booking,
            self::Note, self::MonthSummary, self::WeekBalance, self::YearHeatmap, self::Absences,
            self::HomeOffice, self::Meetings, self::Activity => WidgetGroup::Time,
            self::Todos, self::TodoTags, self::TodoProgress => WidgetGroup::Tasks,
            default => WidgetGroup::Development,
        };
    }

    public function view(): string
    {
        return 'widgets.'.str_replace('_', '-', $this->value);
    }

    /** Columns out of six — the width a widget gets unless the user says otherwise. */
    public function span(): int
    {
        return match ($this) {
            self::Timer, self::Stats, self::YearHeatmap => 6,
            self::Activity => 4,
            self::WeekChart, self::Todos, self::Entries, self::CommitsToday,
            self::ProjectLauncher, self::ReviewQueue, self::MyPullRequests, self::ApprovedPulls,
            self::Tickets, self::Packages, self::Docker, self::Runs => 4,
            default => 2,
        };
    }

    /** Grid rows — a tile has a fixed height, its content scrolls inside. */
    public function rows(): int
    {
        return match ($this) {
            self::Timer, self::Stats, self::TodoTags, self::TodoProgress => 2,
            self::Booking => 8,
            self::WeekChart, self::Todos, self::CommitsToday, self::ReviewQueue,
            self::MyPullRequests, self::Snippets, self::TestPost,
            self::Tickets, self::Packages, self::Docker, self::Runs => 4,
            // shorter than its siblings on purpose: approved-and-open is a handful, not a backlog
            self::ApprovedPulls => 3,
            default => 3,
        };
    }

    /**
     * The silhouette the gallery draws — what the tile looks like, not what is in it. A
     * scaled-down copy of the real widget is unreadable at gallery width, a schematic of
     * its shape is not.
     */
    public function shape(): string
    {
        return match ($this) {
            self::Timer => 'timer',
            self::Stats, self::MonthSummary, self::HomeOffice, self::TodoProgress => 'metrics',
            self::WeekChart, self::WeekBalance => 'chart',
            self::YearHeatmap => 'heatmap',
            self::Meetings, self::Activity => 'list',
            self::Entries, self::Absences, self::Todos, self::CommitsToday, self::CommitsWeek,
            self::ReviewQueue, self::MyPullRequests, self::ApprovedPulls, self::Snippets => 'list',
            self::Booking, self::TestPost => 'form',
            self::Note => 'text',
            self::TodoTags, self::DevLinks => 'pills',
            self::ProjectLauncher => 'buttons',
            self::Tickets, self::Packages, self::Docker, self::Releases, self::Runs => 'list',
        };
    }

    /** Widgets that reach out to GitHub, so the dashboard only pays for them when shown. */
    public function isRemote(): bool
    {
        return in_array($this, [self::ReviewQueue, self::MyPullRequests, self::ApprovedPulls], true);
    }

    /** @return array<int, self> The dashboard everybody starts with — today's default view. */
    public static function defaults(): array
    {
        return [
            self::Timer,
            self::Stats,
            self::WeekChart,
            self::Note,
            self::Todos,
            self::Booking,
            self::Entries,
        ];
    }
}
