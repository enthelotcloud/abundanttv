<?php

use Illuminate\Support\Facades\Route;


Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('dashboard', 'admin::dashboard')->name('dashboard');
    Route::livewire('shows', 'admin::show-manager')->name('shows');
    Route::livewire('presenters', 'admin::presenter-manager')->name('presenters');
    Route::livewire('schedules', 'admin::schedule-manager')->name('schedules');
    Route::livewire('contacts', 'admin::contacts-manager')->name('contacts');
    Route::livewire('contactforms', 'admin::contactform-manager')->name('contactforms');
    Route::livewire('faqs', 'admin::faqs-manager')->name('faqs');
    Route::livewire('comments', 'admin::comments-manager')->name('comments');
    Route::livewire('bannedwords', 'admin::bannedwords-manager')->name('bannedwords');
    Route::livewire('users', 'admin::users-manager')->name('users');
    Route::livewire('viewlogs', 'admin::views')->name('viewlogs');
});

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('about', 'pages::about')->name('about');
Route::livewire('faqs', 'pages::faqs')->name('faqs');
Route::livewire('contact', 'pages::contact')->name('contact');
Route::livewire('schedule', 'pages::schedule')->name('schedule');

require __DIR__.'/settings.php';
