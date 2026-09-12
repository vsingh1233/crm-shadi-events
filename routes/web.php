<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Models\FollowUp;
use App\Models\Lead;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::get('/pending-approval', function () {
    return auth()->user()->is_active ? redirect()->route('dashboard') : view('auth.pending-approval');
})->middleware('auth')->name('approval.pending');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $leads = Lead::visibleTo($user);
        $followUps = FollowUp::whereHas('lead', fn ($q) => $q->visibleTo($user))->where('status', 'pending');

        return view('dashboard', [
            'total' => (clone $leads)->count(),
            'open' => (clone $leads)->whereNotIn('status', ['won', 'lost'])->count(),
            'won' => (clone $leads)->where('status', 'won')->count(),
            'overdue' => (clone $followUps)->where('due_at', '<', now())->count(),
            'upcoming' => (clone $followUps)->with('lead', 'responsible')->orderBy('due_at')->limit(6)->get(),
            'recent' => (clone $leads)->with('owner')->latest()->limit(5)->get(),
        ]);
    })->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->whereNumber('lead')->name('leads.show');
    Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->whereNumber('lead')->name('leads.edit');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::patch('/leads/{lead}/team', [LeadController::class, 'team'])->name('leads.team');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
    Route::post('/leads/{lead}/restore', [LeadController::class, 'restore'])->whereNumber('lead')->name('leads.restore');
    Route::post('/leads/{lead}/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::get('/follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
    Route::post('/leads/{lead}/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::patch('/leads/{lead}/follow-ups/{followUp}', [FollowUpController::class, 'update'])->name('follow-ups.update');
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::patch('/team/{user}', [TeamController::class, 'update'])->name('team.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}',[NotificationController::class, 'read'])->name('notifications.read');
});
require __DIR__.'/auth.php';
