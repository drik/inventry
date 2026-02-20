<?php

use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invitations/{token}/accept', [InvitationController::class, 'show'])
    ->name('invitation.accept');

Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->name('invitation.accept.store');
