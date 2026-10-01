<?php

use Illuminate\Support\Facades\Route;

// La SPA React est servie pour toute URL hors /api
Route::view('/{any?}', 'app')->where('any', '^(?!api).*$');
