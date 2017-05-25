<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Services\SilverpopService;

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

/**
 * databases
 */
Route::get('/databases/{database_id?}', 'Api\DatabaseController@list');

Route::get('/databases/{database_id}/count', 'Api\DatabaseController@size');

Route::get('/databases/{database_id}/export', 'Api\DatabaseController@export');

Route::get('/databases/{database_id}/contacts/{email}', 'Api\DatabaseController@listContactLists');

Route::post('/databases/{database_id}/contact', 'Api\DatabaseController@createContact');

/**
 * jobs
 */
Route::get('/jobs/{job_id}', function (SilverpopService $silverpop, $jobId) {
    try {
        $result = $silverpop->getJobStatus($jobId);
    } catch (Exception $e) {
        throw $e;
    };

    $response = [
        'results' => [
            'job_status' => $result
        ]
    ];

    return $response;
});

Route::get('/jobs/{job_id}/download', function (SilverpopService $silverpop, $jobId) {
    $hash = sha1($jobId);

    if (!Cache::has($hash)) {
        throw new Exception('Job ID not found.');
    }

    $job = Cache::get($hash);

    $localFile = tmpfile();
    $serverFile = $job['filePath'][0];

    try {
        if (!Storage::exists($hash)) {
            // get job status
            $jobStatus = $silverpop->getJobStatus($jobId);

            if ($jobStatus !== 'COMPLETE') {
                throw new Exception('Job status [' . $jobStatus . '] is not complete.');
            }

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

/**
 * templates
 */
Route::post('/templates/{template_id}/schedule', function (SilverpopService $silverpop, Request $request, $templateId) {
    $input = $request->all();

    if (!is_array($input)) {
        throw new Exception('Illegal input.');
    }

    if (empty($input)) {
        throw new Exception('Missing input.');
    }

    $defaultInput = [
        'listId' => null,
        'mailingName' => uniqid('random-'),
        'scheduledTimestamp' => time() + 60,
        'optionalElements' => [],
        'saveToSharedFolder' => 1,
        'suppressionLists' => []
    ];

    $input = array_merge($defaultInput, $input);

    try {
        // scheduleMailing($templateId, $listId, $mailingName, $scheduledTimestamp, $optionalElements = array(), $saveToSharedFolder = 0, $suppressionLists = array())
        $mailingId = $silverpop->scheduleMailing(
            $templateId,
            $input['listId'],
            $input['mailingName'],
            $input['scheduledTimestamp'],
            $input['optionalElements'],
            $input['saveToSharedFolder'],
            $input['suppressionLists']
        );

        $mailingId = json_decode(json_encode($mailingId), true);
    } catch (Exception $e) {
        throw $e;
    }

    $response = [
        'results' => [
            'mailingId' => $mailingId[0]
        ]
    ];

    return $response;
});

/**
 * webhook
 */
Route::post('/templates/webhook', function (SilverpopService $silverpop, Request $request) {
    $input = $request->all();

    $to = $request->input('to');
    $subject = $request->input('subject');
    $body = [
        'html' => $request->input('html')
    ];

    // check if list is valid
    $list = $silverpop->getLists(strtok($to, '@'));

    $listId = $list['ID'];

    // disable xml errors
    libxml_use_internal_errors(true);

    $dom = new DOMdocument();
    $dom->loadHTML(mb_convert_encoding($body['html'], 'HTML-ENTITIES', 'UTF-8'));
    $xPath = new DOMXPath($dom);
    $bodyTag = $xPath->query('//body')->item(0);
    $e = $dom->createElement('a', 'Unsubscribe');
    $e->setAttribute('href', '#SPONECLICKOPTOUT');
    $e->setAttribute('xt', 'SPONECLICKOPTOUT');
    $e->setAttribute('name', 'OOLink');
    $a = $bodyTag->appendChild($e);

    $body['html'] = $dom->saveHTML();

    // enable xml errors
    libxml_use_internal_errors(false);

    $clickThroughs = [
        [
            'ClickThroughName' => 'OOLink',
            'ClickThroughURL' => '#SPONECLICKOPTOUT',
            'ClickThroughType' => 14
        ]
    ];

    try {
        // createTemplate($mailingSubject, $mailingBodies = [], $mailingFromName, $mailingFromEmail, $mailingReplyTo, $listId, $clickThroughs)
        $template = $silverpop->createTemplate(
            $subject,
            $body,
            'NCSY',
            'info@ncsy.org',
            'info@ncsy.org',
            $listId,
            $clickThroughs
        );

        $templateId = $template['MailingID'];

        $mailingId = $silverpop->scheduleMailing(
            $templateId,
            $listId,
            $subject . '-' . time(),
            time() + 20,
            [],
            1,
            []
        );
    } catch (Exception $e) {
        throw $e;
    }

    $response = [
        'results' => [
            'templateId' => $templateId,
            'mailingId' => $mailingId[0]
        ]
    ];

    return $response;
});
