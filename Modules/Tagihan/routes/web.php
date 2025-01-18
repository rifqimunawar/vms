<?php

use Illuminate\Support\Facades\Route;
use Modules\Tagihan\Http\Controllers\PamController;
use Modules\Tagihan\Http\Controllers\TagihanController;
use Modules\Tagihan\Http\Controllers\UmumController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::prefix('tagihan')->middleware('auth')->group(function () {

  Route::get('/umum', [UmumController::class, 'index'])->name('umum.index');
  Route::get('/umum/create', [UmumController::class, 'create'])->name('umum.create');
  Route::get('/umum/export', [UmumController::class, 'export'])->name('umum.export');
  Route::get('/umum/pdf', [UmumController::class, 'pdf'])->name('umum.pdf');
  Route::get('/umum/print', [UmumController::class, 'print'])->name('umum.print');
  Route::get('/umum/{id}', [UmumController::class, 'edit'])->name('umum.edit');
  Route::get('/umum/{id}/view', [UmumController::class, 'view'])->name('umum.view');
  Route::post('/umum/store', [UmumController::class, 'store'])->name('umum.store');
  Route::delete('/umum/{id}/del', [UmumController::class, 'destroy'])->name('umum.destroy');

  Route::get('/pam', [PamController::class, 'index'])->name('pam.index');
  Route::get('/pam/create', [PamController::class, 'create'])->name('pam.create');
  Route::get('/pam/export', [PamController::class, 'export'])->name('pam.export');
  Route::get('/pam/pdf', [PamController::class, 'pdf'])->name('pam.pdf');
  Route::get('/pam/print', [PamController::class, 'print'])->name('pam.print');
  Route::get('/pam/{id}', [PamController::class, 'edit'])->name('pam.edit');
  Route::get('/pam/{id}/view', [PamController::class, 'view'])->name('pam.view');
  Route::post('/pam/store', [PamController::class, 'store'])->name('pam.store');
  Route::delete('/pam/{id}/del', [PamController::class, 'destroy'])->name('pam.destroy');
});
