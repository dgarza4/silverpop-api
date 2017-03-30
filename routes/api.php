<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

Route::get('/list', function ($type = 2) {
    try {
        $silverpop = new EngagePod([
            'username' => env('SILVERPOP_API_USERNAME'),
            'password' => env('SILVERPOP_API_PASSWORD'),
            'engage_server' => env('SILVERPOP_API_POD')
        ]);

        $lists = $silverpop->getLists($type, false);

        $response = [
            'results' => $lists
        ];

        return $response;

    } catch (Exception $e) {
        throw $e;
    };
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

        $hash = sha1($exportList['JOB_ID']);

        Cache::put($hash, $exportList, 60 * 24);

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
    $hash = sha1($id);

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
    $hash = sha1($id);

    if (!Cache::has($hash)) {
        throw new Exception('Job ID not found.');
    }

    $job = Cache::get($hash);

    $localFile = tmpfile();
    $serverFile = $job['FILE_PATH'];

    try {
        if (!Storage::exists($hash)) {
            $conn_id = ftp_connect('transfer' . env('SILVERPOP_API_POD') . '.silverpop.com');
            $login_result = ftp_login($conn_id, env('SILVERPOP_API_USERNAME'), env('SILVERPOP_API_PASSWORD'));

            if (!ftp_fget($conn_id, $localFile, $serverFile, FTP_BINARY)) {
                throw new Exception('There was a problem downloading the file.');
            }

            ftp_close($conn_id);

            Storage::put($hash, $localFile);

            fclose($localFile);
        }

        $headers = [
            'Content-Type' => 'text/csv',
        ];

        $fs = Storage::getDriver();
        $stream = $fs->readStream($hash);

        return Response::stream(function () use ($hash, $stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-disposition' => 'attachment; filename="' . $hash . '"',
        ]);
    } catch (Exception $e) {
        throw $e;
    };
});
