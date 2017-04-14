<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Services\SilverpopService;

class DatabaseController extends Controller
{
    /**
     * The silverpop service instance.
     */
    protected $silverpop;

    /**
     * Create a new controller instance.
     *
     * @param  SilverpopService $silverpop
     * @return void
     */
    public function __construct(SilverpopService $silverpop)
    {
        $this->silverpop = $silverpop;
    }

    /**
     * Get a list of databases.
     *
     * @param  int  $databaseId
     * @return Response
     */
    public function list($databaseId = null)
    {
        try {
            $lists = $this->silverpop->getLists($databaseId);
        } catch (Exception $e) {
            throw $e;
        }

        $response = [
            'results' => $lists
        ];

        return $response;
    }

    /**
     * Get this size of a database.
     *
     * @param  int  $databaseId
     * @return Response
     */
    public function size($databaseId)
    {
        try {
            $count = $this->silverpop->getListCount($databaseId);
        } catch (Exception $e) {
            throw $e;
        }

        $response = [
            'results' => [
                'count' => $count
            ]
        ];

        return $response;
    }

    /**
     * Initiate export of a database.
     *
     * @param  int  $databaseId
     * @return Response
     */
    public function export($databaseId)
    {
        try {
            $exportList = $this->silverpop->exportList($databaseId);
        } catch (Exception $e) {
            throw $e;
        };

        $response = [
            'results' => [
                'export' => $exportList
            ]
        ];

        return $response;
    }

    /**
     * Get contact lists for contact.
     *
     * @param  int    $databaseId
     * @param  string $email
     * @return Response
     */
    public function listContactLists($databaseId, $email)
    {
        try {
            $fields = [
                'RETURN_CONTACT_LISTS' => true,
                'EMAIL' => $email
            ];
            $result = json_decode(json_encode($this->silverpop->selectRecipientData($databaseId, $fields)), true);

            if (empty($result['CONTACT_LISTS'])) {
                $result['CONTACT_LISTS']['CONTACT_LIST_ID'] = [];
            }

            $contactLists = [];
            foreach ($result['CONTACT_LISTS']['CONTACT_LIST_ID'] as $contactListId) {
                $contactLists[$contactListId] = $this->silverpop->getLists($contactListId);
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
    }

    /**
     * Create contact in database.
     *
     * @param  int    $databaseId
     * @return Response
     */
    public function createContact(Request $request, $databaseId)
    {
        $input = $request->all();

        if (!is_array($input)) {
            throw new Exception('Illegal input.');
        }

        if (empty($input)) {
            throw new Exception('Missing input.');
        }

        $defaultInput = [
            'fields' => [],
            'upsert' => false,
            'autoreply' => false,
            'createdFrom' => \SilverpopConnector\SilverpopXmlConnector::CREATED_FROM_MANUAL,
            'contactLists' => []
        ];

        $input = array_merge($defaultInput, $input);

        try {
            $contact = $this->silverpop->addRecipient($databaseId, $input['fields'], $input['upsert'], $input['autoreply'], $input['createdFrom'], $input['contactLists']);
        } catch (Exception $e) {
            throw $e;
        }

        $response = [
            'results' => $contact
        ];

        return $response;
    }
}
