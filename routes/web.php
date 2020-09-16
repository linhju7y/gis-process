<?php

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

Route::group(['prefix' => 'exam'], function() {
    Route::get("/", 'ExamController@index');
    Route::get("cadastral", 'ExamController@cadastral');
    Route::get("rotatewgs84", 'ExamController@rotatewgs84');
    Route::get("hcmgis", "ExamController@hcmgis");
    Route::get("street", "ExamController@street");
});

Route::group(['prefix' => 'api'], function() {
    Route::get("get-subdivision/{id?}", "ApiController@getSubdivision");
});

Route::group(['prefix' => 'geo'], function() {
    Route::match(['get', 'post'], "import-land", "GeoController@land");
    Route::match(['get', 'post'], "flush-land", "GeoController@flushland");
    Route::get("tsss", "GeoController@visualtsss");
});