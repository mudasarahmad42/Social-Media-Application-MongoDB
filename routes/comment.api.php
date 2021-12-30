<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CommentController;

Route::middleware(['Cauth'])->group(function () {
//Comments
Route::post('/comment/{id}', [CommentController::class, 'create']);
Route::put('/comment/{id}', [CommentController::class, 'update']);
Route::delete('/comment/{id}', [CommentController::class, 'delete']);
Route::get('/myComments', [CommentController::class, 'myComments']);
});
?>