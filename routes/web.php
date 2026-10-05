<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// SPA: o Vue Router cuida das rotas do front.
Route::view('/{any?}', 'app')->where('any', '^(?!api|docs|horizon|pulse|up).*$')->name('spa');
