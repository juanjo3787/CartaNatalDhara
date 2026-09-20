<?php

use App\Http\Controllers\ChartController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/charts');

Route::get('/charts', [ChartController::class, 'index'])->name('charts.index');
Route::get('/charts/create', [ChartController::class, 'create'])->name('charts.create');
Route::post('/charts', [ChartController::class, 'store'])->name('charts.store');
Route::delete('/charts/{chart}', [ChartController::class, 'destroy'])->name('charts.destroy');
Route::post('/charts/{chart}/report/ai/{door}', [ChartController::class, 'generateAiReport'])->name('charts.report.ai');
Route::get('/charts/{chart}/report/edit', [ChartController::class, 'editReport'])->name('charts.report.edit');
Route::put('/charts/{chart}/report', [ChartController::class, 'saveReport'])->name('charts.report.save');
Route::get('/charts/{chart}/report/download', [ChartController::class, 'downloadReport'])->name('charts.report.download');
Route::post('/charts/{chart}/report/regenerate', [ChartController::class, 'regenerateReport'])->name('charts.report.regenerate');
Route::post('/charts/{chart}/report/validate', [ChartController::class, 'validateAndStoreReport'])->name('charts.report.validate');
Route::get('/charts/{chart}/registration/edit', [ChartController::class, 'editRegistration'])->name('charts.registration.edit');
Route::put('/charts/{chart}/registration', [ChartController::class, 'updateRegistration'])->name('charts.registration.update');
Route::get('/charts/{chart}/report/history', [ChartController::class, 'reportHistory'])->name('charts.report.history');
Route::get('/charts/{chart}/person/history', [ChartController::class, 'personHistory'])->name('charts.person.history');
Route::get('/charts/{chart}/report', [ChartController::class, 'report'])->name('charts.report');
Route::get('/charts/{chart}', [ChartController::class, 'show'])->name('charts.show');
