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

Route::get('/list/{id}/export', function ($id) {
    try {
        $silverpop = new EngagePod([
            'username' => env('SILVERPOP_API_USERNAME'),
            'password' => env('SILVERPOP_API_PASSWORD'),
            'engage_server' => env('SILVERPOP_API_POD')
        ]);

        $exportList = $silverpop->exportList($id);

        $response = [
            'results' => [
                'export' => $exportList
            ]
        ];

        return $response;

    } catch (Exception $e) {
        throw $e;
    };
});

Route::get('/job/{id}', function ($id) {
    try {
        $silverpop = new EngagePod([
            'username' => env('SILVERPOP_API_USERNAME'),
            'password' => env('SILVERPOP_API_PASSWORD'),
            'engage_server' => env('SILVERPOP_API_POD')
        ]);

        $result = $silverpop->getJobStatus($id);

        $response = [
            'results' => [
                'job_status' => $result
            ]
        ];

        return $response;

    } catch (Exception $e) {
        throw $e;
    };
});

Route::get('/job/{id}/download', function ($id) {
    $localFile = '/tmp/' . sha1($id);
    $serverFile = '/download/TJJ IL NEW - All - Mar 30 2017 12-29-00 AM.CSV';

    try {
        $conn_id = ftp_connect('transfer' . env('SILVERPOP_API_POD') . '.silverpop.com');
        $login_result = ftp_login($conn_id, env('SILVERPOP_API_USERNAME'), env('SILVERPOP_API_PASSWORD'));

        if (ftp_get($conn_id, $localFile, $serverFile, FTP_BINARY)) {
            echo "Successfully written to $localFile\n";
        } else {
            echo "There was a problem\n";
        }

        ftp_close($conn_id);

        $response = [
            'results' => [
                'file' => $localFile
            ]
        ];

        return $response;
    } catch (Exception $e) {
        throw $e;
    };
});
