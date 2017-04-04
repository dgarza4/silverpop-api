<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use SilverpopConnector\SilverpopConnector;

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

Route::get('/contact/{database_id}/{email}', function (SilverpopConnector $silverpop, $databaseId, $email) {
    try {
        $silverpop->authenticateXml(
            config('services.silverpop.username'),
            config('services.silverpop.password')
        );

        $fields = [
            'RETURN_CONTACT_LISTS' => true,
            'EMAIL' => $email
        ];
        $result = json_decode(json_encode($silverpop->selectRecipientData($databaseId, $fields)), true);

        if (empty($result['CONTACT_LISTS'])) {
            $result['CONTACT_LISTS']['CONTACT_LIST_ID'] = [];
        }
    } catch (Exception $e) {
        throw $e;
    };

    $response = [
        'results' => [
            'contactListId' => $result['CONTACT_LISTS']['CONTACT_LIST_ID']
        ]
    ];

    return $response;
});

Route::get('/list/{id?}', function (SilverpopConnector $silverpop, $id = null) {
    $hash = sha1('api/list');

    if (Cache::has($hash)) {
        $lists = Cache::get($hash);
    } else {
        try {
            $silverpop->authenticateXml(
                config('services.silverpop.username'),
                config('services.silverpop.password')
            );

            $result = json_decode(json_encode($silverpop->getLists()), true);

            $lists = [];
            foreach ($result as $item) {
                $id = $item['ID'];
                $lists[$id] = $item;
            }

            Cache::put($hash, $lists, 15);
        } catch (Exception $e) {
            throw $e;
        };
    }

    $response = [
        'results' => $id ? $lists[$id] : $lists
    ];

    return $response;
});

Route::get('/list/{id}/count', function (SilverpopConnector $silverpop, $id) {
    $listHash = sha1('api/list');

    $lists = [];
    if (Cache::has($listHash)) {
        $lists = Cache::get($listHash);
    }

    if (array_key_exists($id, $lists)) {
        $listMetaData = $lists[$id];
    } else {
        try {
            $silverpop->authenticateXml(
                config('services.silverpop.username'),
                config('services.silverpop.password')
            );

            $listMetaData = json_decode(json_encode($silverpop->getListMetaData($id)), true);
        } catch (Exception $e) {
            throw $e;
        };
    }

    $response = [
        'results' => [
            'count' => $listMetaData['SIZE']
        ]
    ];

    return $response;
});

Route::get('/list/{id}/export', function (SilverpopConnector $silverpop, $id) {
    try {
        $silverpop->authenticateXml(
            config('services.silverpop.username'),
            config('services.silverpop.password')
        );

        $exportList = json_decode(json_encode($silverpop->exportList($id)), true);

        $hash = sha1($exportList['jobId'][0]);

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

Route::get('/job/{id}', function (SilverpopConnector $silverpop, $id) {
    $hash = sha1($id);

    try {
        $silverpop->authenticateXml(
            config('services.silverpop.username'),
            config('services.silverpop.password')
        );

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
    $serverFile = $job['filePath'][0];

    try {
        if (!Storage::exists($hash)) {
            $conn_id = ftp_connect('transfer' . config('services.silverpop.engage_server') . '.silverpop.com');

            $login_result = ftp_login($conn_id, config('services.silverpop.username'), config('services.silverpop.password'));

            ftp_pasv($conn_id, true);
            ftp_set_option($conn_id, FTP_TIMEOUT_SEC, 1);

            $size = ftp_size($conn_id, $serverFile);

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
