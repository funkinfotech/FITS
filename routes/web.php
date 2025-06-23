<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Models\Ticket;

Route::middleware(['auth'])->group(function () {
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
});

Route::get('/access-denied', function () {
    return view('errors.access-denied');
})->name('access-denied');

//Route::view('/', 'welcome');
Route::get('/', function () {
    $taglines = [
        'Tech support that speaks human.',
        'Smooth tech, happy clients.',
        'Click. Submit. Solved.',
        'FunkIT: Where bugs go to die.',
        'Helping your tech help you.',
        'Support without the hold music.',
        'From frazzled to functional, fast.',
        'Tech support that doesn’t miss a beat 🎶',
        'Tech troubles? FunkIT has your back. 💻🛠️',
        'We handle the IT stress so you don’t have to. 😮‍💨🧘',
        'Support that actually supports you. 🙌📞',
        'No nonsense. Just solid tech help. ⚡🔧',
        'Solving your tech mess with finesse. 🎯🖥️',
        '🤓 Simple. Smart. Support. ✅',
        'When IT hits the fan, FunkIT. 💩💥',
        'Your business, running smoother—one ticket at a time. 🧾🚀',
        '🧑‍💻 Real people. Real fixes. Real fast. ⚙️',
    ];

    $tagline = $taglines[array_rand($taglines)];

    return view('welcome', compact('tagline'));
});

Route::get('/dashboard', function () {
    $tickets = Ticket::where('email', auth()->user()->email)->get();
    return view('dashboard', compact('tickets'));
})->middleware(['auth'])->name('dashboard');

Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
