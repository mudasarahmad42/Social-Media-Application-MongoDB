<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::middleware(['Cauth'])->group(function () {
 //Users Routes
 Route::get('/users/myprofile', [UserController::class, 'myProfile']);
 Route::put('/users/update', [UserController::class, 'update']);
 Route::delete('/users/delete', [UserController::class, 'delete']);
 Route::post('/users/search/{name}', [UserController::class, 'searchByName']);
});
