<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//TODO: need one api to verify email so that i can create a link in profile view page to send verification email if user has not verified their email yet. This is needed because i am using email verification in registration process but some users may have registered before i implemented email verification, so they will not have received verification email and they will be stuck with unverified email status. This api will allow them to request a new verification email to be sent to them.
