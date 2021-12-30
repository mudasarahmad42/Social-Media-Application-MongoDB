<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FriendRequestController;

Route::middleware(['Cauth'])->group(function () {
   //Friend Request
   Route::post('/sendRequest/{id}', [FriendRequestController::class, 'sendRequest']);
   Route::get('/myRequests', [FriendRequestController::class, 'myRequests']);
   Route::get('/acceptRequest/{id}', [FriendRequestController::class, 'acceptRequest']);
   Route::get('/deleteRequest/{id}', [FriendRequestController::class, 'deleteRequest']);
   Route::get('/removeFriend/{id}', [FriendRequestController::class, 'removeFriend']);

});
?>