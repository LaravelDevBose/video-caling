<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
Route::post('/video/call-user', 'HomeController@call_to_user'); // ajax call
Route::post('/video/accept-call', 'HomeController@acceptCall'); // ajax call
Route::post('/video/decline-call', 'HomeController@declineCall'); // ajax call
Route::post('/video/decline-call', 'HomeController@declineCall'); // ajax call
Route::post('/video/another-call', 'HomeController@onAnotherCall'); // ajax call
Route::post('/video/end-call', 'HomeController@endCall'); // ajax call
