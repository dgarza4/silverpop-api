<?php

use Illuminate\Http\Request;
use Silverpop\EngagePod;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/list/{id}/count', function ($id) {
    try {
        $silverpop = new EngagePod([
            'username' => env('SILVERPOP_API_USERNAME'),
            'password' => env('SILVERPOP_API_PASSWORD'),
            'engage_server' => env('SILVERPOP_API_POD')
        ]);

        $listMetaData = $silverpop->getListMetaData($id);

        $response = [
            'results' => [
                'count' => $listMetaData['SIZE']
            ]
        ];

        return $response;

    } catch (Exception $e) {
        throw $e;
    };
});
