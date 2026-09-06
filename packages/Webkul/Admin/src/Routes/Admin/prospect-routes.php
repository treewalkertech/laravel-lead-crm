<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Prospect\ProspectContactController;
use Webkul\Admin\Http\Controllers\Prospect\ProspectController;

Route::controller(ProspectController::class)->prefix('prospects')->group(function () {
    Route::get('', 'index')->name('admin.prospects.index');

    Route::get('create', 'create')->name('admin.prospects.create');

    Route::post('create', 'store')->name('admin.prospects.store');

    Route::get('edit/{id}', 'edit')->name('admin.prospects.edit');

    Route::put('edit/{id}', 'update')->name('admin.prospects.update');

    Route::delete('{id}', 'destroy')->name('admin.prospects.delete');

    Route::post('mass-destroy', 'massDestroy')->name('admin.prospects.mass_delete');

    Route::put('contacts/{id}/convert', [ProspectContactController::class, 'convertToLead'])->name('admin.prospects.contacts.convert');
});
