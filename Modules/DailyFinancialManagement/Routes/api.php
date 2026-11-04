<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['api', 'auth:api']], function () {
    Route::get('/summary', 'ApiController@summary');
    Route::get('/branches', 'ApiController@branches');
    Route::get('/closing-status', 'ApiController@closingStatus');
    Route::post('/expenses', 'ApiController@createExpense');
    Route::post('/sync', 'ApiController@triggerSync');
});

