<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Services\SilverpopService;
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

Route::get('/contact/{database_id}/{email}', function (SilverpopService $silverpop, $databaseId, $email) {
    try {
        $fields = [
            'RETURN_CONTACT_LISTS' => true,
            'EMAIL' => $email
        ];
        $result = json_decode(json_encode($silverpop->selectRecipientData($databaseId, $fields)), true);

        if (empty($result['CONTACT_LISTS'])) {
            $result['CONTACT_LISTS']['CONTACT_LIST_ID'] = [];
        }

        $contactLists = [];
        foreach ($result['CONTACT_LISTS']['CONTACT_LIST_ID'] as $contactListId) {
            $contactLists[$contactListId] = $silverpop->getLists($contactListId);
        }
    } catch (Exception $e) {
        throw $e;
    };

    $response = [
        'results' => [
            'contactLists' => $contactLists
        ]
    ];

    return $response;
});

Route::get('/list/{id?}', function (SilverpopService $silverpop, $id = null) {
    try {
        $lists = $silverpop->getLists($id);
    } catch (Exception $e) {
        throw $e;
    }

    $response = [
        'results' => $lists
    ];

    return $response;
});

Route::get('/list/{id}/count', function (SilverpopService $silverpop, $id) {
    try {
        $count = $silverpop->getListCount($id);
    } catch (Exception $e) {
        throw $e;
    }

    $response = [
        'results' => [
            'count' => $count
        ]
    ];

    return $response;
});

Route::get('/list/{id}/export', function (SilverpopService $silverpop, $id) {
    try {
        $exportList = $silverpop->exportList($id);
    } catch (Exception $e) {
        throw $e;
    };

    $response = [
        'results' => [
            'export' => $exportList
        ]
    ];

    return $response;
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
