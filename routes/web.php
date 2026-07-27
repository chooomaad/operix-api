<?php

use Illuminate\Support\Facades\Route;

// SPA shell — toutes les routes non-API pointent vers le même blade
Route::get('/{any?}', fn () => view('app'))->where('any', '.*');
