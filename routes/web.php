<?php

declare(strict_types=1);

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardLayoutController;
use App\Http\Controllers\DayNoteController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\MonthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SnippetController;
use App\Http\Controllers\StepTemplateController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TimerController;
use App\Http\Controllers\TodoAttachmentController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\TodoStepController;
use App\Http\Controllers\TrashController;
use Illuminate\Support\Facades\Route;

Route::get('/kalender/{token}.ics', [CalendarController::class, 'feed'])
    ->where('token', '[A-Za-z0-9]{16,64}')
    ->middleware('throttle:60,1')
    ->name('calendar.feed');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/registrieren', [RegisterController::class, 'create'])->name('register');
    Route::post('/registrieren', [RegisterController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::put('/dashboard/anordnung', [DashboardLayoutController::class, 'arrange'])->name('dashboard.arrange');
    Route::post('/dashboard/anordnung/zuruecksetzen', [DashboardLayoutController::class, 'reset'])->name('dashboard.reset');
    Route::get('/verlauf', HistoryController::class)->name('history');

    Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar');

    Route::get('/kalender/abwesenheiten', [AbsenceController::class, 'index'])->name('absences');
    Route::post('/kalender/abwesenheiten', [AbsenceController::class, 'store'])->name('absences.store');
    Route::delete('/kalender/abwesenheiten/{absence}', [AbsenceController::class, 'destroy'])->name('absences.destroy');

    Route::post('/notizen', [DayNoteController::class, 'store'])->name('notes.store');

    Route::get('/entwicklung', [DeveloperController::class, 'index'])->name('dev');
    Route::post('/entwicklung/reviews', [DeveloperController::class, 'refreshReviews'])->name('dev.reviews');
    Route::get('/entwicklung/testpost', [DeveloperController::class, 'post'])->name('dev.testpost');
    Route::post('/entwicklung/testpost/slack', [DeveloperController::class, 'send'])->name('dev.testpost.send');

    Route::get('/entwicklung/projekte', [ProjectController::class, 'index'])->name('projects');
    Route::post('/entwicklung/projekte', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/entwicklung/projekte/scan', [ProjectController::class, 'scan'])->name('projects.scan');
    Route::put('/entwicklung/projekte/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/entwicklung/projekte/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('/entwicklung/projekte/{project}/start', [ProjectController::class, 'start'])->name('projects.start');
    Route::post('/entwicklung/projekte/{project}/stop', [ProjectController::class, 'stop'])->name('projects.stop');

    Route::get('/entwicklung/bausteine', [SnippetController::class, 'index'])->name('snippets');
    Route::post('/entwicklung/bausteine', [SnippetController::class, 'store'])->name('snippets.store');
    Route::put('/entwicklung/bausteine/{snippet}', [SnippetController::class, 'update'])->name('snippets.update');
    Route::delete('/entwicklung/bausteine/{snippet}', [SnippetController::class, 'destroy'])->name('snippets.destroy');
    Route::post('/entwicklung/bausteine/{snippet}/kopiert', [SnippetController::class, 'used'])->name('snippets.used');

    Route::get('/auswertung', InsightsController::class)->name('insights');
    Route::get('/auswertung/bericht', [InsightsController::class, 'report'])->name('insights.report');
    Route::get('/suche', SearchController::class)->name('search');

    Route::get('/monat/stundenzettel', [MonthController::class, 'timesheet'])->name('month.timesheet');
    Route::get('/monat/csv', [MonthController::class, 'csv'])->name('month.csv');
    Route::get('/export', [BackupController::class, 'download'])->name('backup');
    Route::post('/export', [BackupController::class, 'restore'])->name('backup.restore');

    Route::get('/todo', [TodoController::class, 'index'])->name('todos.index');
    Route::post('/todo', [TodoController::class, 'store'])->name('todos.store');
    Route::delete('/todo/erledigte', [TodoController::class, 'destroyCompleted'])->name('todos.clear');
    Route::get('/todo/tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('/todo/tags', [TagController::class, 'store'])->name('tags.store');
    Route::put('/todo/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('/todo/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

    Route::get('/todo/vorlagen', [StepTemplateController::class, 'index'])->name('templates');
    Route::post('/todo/vorlagen', [StepTemplateController::class, 'store'])->name('templates.store');
    Route::delete('/todo/vorlagen/{template}', [StepTemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('/todo/{todo}/vorlage', [StepTemplateController::class, 'apply'])->name('templates.apply');
    Route::post('/todo/{todo}/als-vorlage', [StepTemplateController::class, 'fromTodo'])->name('templates.from-todo');

    Route::patch('/todo/{todo}/verschieben', [TodoController::class, 'snooze'])->name('todos.snooze');

    Route::get('/todo/{todo}', [TodoController::class, 'show'])->name('todos.show');
    Route::get('/todo/{todo}/bearbeiten', [TodoController::class, 'edit'])->name('todos.edit');
    Route::put('/todo/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::patch('/todo/{todo}/erledigt', [TodoController::class, 'toggle'])->name('todos.toggle');
    Route::delete('/todo/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');

    Route::post('/todo/{todo}/schritte', [TodoStepController::class, 'store'])->name('steps.store');
    Route::patch('/todo/{todo}/schritte/{step}', [TodoStepController::class, 'toggle'])->scopeBindings()->name('steps.toggle');
    Route::delete('/todo/{todo}/schritte/{step}', [TodoStepController::class, 'destroy'])->scopeBindings()->name('steps.destroy');

    Route::post('/todo/{todo}/anhaenge', [TodoAttachmentController::class, 'store'])->name('attachments.store');
    Route::get('/todo/{todo}/anhaenge/{attachment}', [TodoAttachmentController::class, 'show'])->scopeBindings()->name('attachments.show');
    Route::delete('/todo/{todo}/anhaenge/{attachment}', [TodoAttachmentController::class, 'destroy'])->scopeBindings()->name('attachments.destroy');

    Route::post('/timer/start', [TimerController::class, 'start'])->name('timer.start');
    Route::post('/timer/stop', [TimerController::class, 'stop'])->name('timer.stop');

    Route::post('/entries', [TimeEntryController::class, 'store'])->name('entries.store');
    Route::get('/entries/{entry}/bearbeiten', [TimeEntryController::class, 'edit'])->name('entries.edit');
    Route::put('/entries/{entry}', [TimeEntryController::class, 'update'])->name('entries.update');
    Route::delete('/entries/{entry}', [TimeEntryController::class, 'destroy'])->name('entries.destroy');

    Route::delete('/tage/{date}', [TimeEntryController::class, 'destroyDay'])
        ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
        ->name('days.destroy');

    Route::get('/papierkorb', [TrashController::class, 'index'])->name('trash');
    Route::patch('/papierkorb/buchungen/{entry}', [TrashController::class, 'restoreEntry'])->withTrashed()->name('trash.entry.restore');
    Route::delete('/papierkorb/buchungen/{entry}', [TrashController::class, 'purgeEntry'])->withTrashed()->name('trash.entry.purge');
    Route::patch('/papierkorb/aufgaben/{todo}', [TrashController::class, 'restoreTodo'])->withTrashed()->name('trash.todo.restore');
    Route::delete('/papierkorb/aufgaben/{todo}', [TrashController::class, 'purgeTodo'])->withTrashed()->name('trash.todo.purge');
    Route::delete('/papierkorb', [TrashController::class, 'empty'])->name('trash.empty');

    Route::get('/einstellungen', [SettingsController::class, 'show'])->name('settings');
    Route::get('/einstellungen/export', [BackupController::class, 'downloadSettings'])->name('settings.export');
    Route::post('/einstellungen/import', [BackupController::class, 'restoreSettings'])->name('settings.import');
    Route::put('/einstellungen/profil', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/einstellungen/arbeitszeit', [SettingsController::class, 'updateWorkTime'])->name('settings.worktime');
    Route::put('/einstellungen/benachrichtigungen', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::put('/einstellungen/entwicklung', [SettingsController::class, 'updateDeveloper'])->name('settings.developer');
    Route::put('/einstellungen/passwort', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::put('/einstellungen/design', [SettingsController::class, 'updateTheme'])->name('settings.theme');
    Route::put('/einstellungen/kalender-token', [SettingsController::class, 'regenerateIcalToken'])->name('settings.ical');
    Route::put('/einstellungen/muster', [SettingsController::class, 'updateDesignStyle'])->name('settings.style');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
