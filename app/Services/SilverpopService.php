<?php

namespace App\Services;

use Exception;

use Cache;

use Illuminate\Support\Facades\Storage;

use SilverpopConnector\SilverpopConnector;

class SilverpopService
{
    private $silverpop;

    public function __construct(SilverpopConnector $silverpop)
    {
        $this->silverpop = $silverpop;
    }

    public function __call($name, $arguments)
    {
        $this->authenticateXml();

        if (method_exists($this->silverpop, $name)) {
            $result = call_user_func_array([$this->silverpop, $name], $arguments);
        } else {
            $result = $this->silverpop->__call($name, $arguments);
        }

        return $result;
    }

    public function authenticateXml()
    {
        $this->silverpop->authenticateXml(
            config('services.silverpop.username'),
            config('services.silverpop.password')
        );
    }

    public function getLists($id = null)
    {
        $hash = sha1('api/list');

        if (Storage::exists('list.json')) {
            Cache::put($hash, json_decode(Storage::get('list.json'), true), 15);
        }

        if (Cache::has($hash)) {
            $lists = Cache::get($hash);
        } else {
            try {
                $this->authenticateXml();

                $fields = [
                    'INCLUDE_ALL_LISTS' => 'true'
                ];
                $result = json_decode(json_encode($this->silverpop->getLists($fields)), true);

                $lists = [];
                foreach ($result as $item) {
                    $itemId = $item['ID'];
                    $lists[$itemId] = $item;
                }

                Cache::put($hash, $lists, 15);
            } catch (Exception $e) {
                throw $e;
            };
        }

        if ($id) {
            if (array_key_exists($id, $lists)) {
                return $lists[$id];
            } else {
                throw new Exception('List ID ' . $id . ' not found');
            }
        } else {
            return $lists;
        }
    }

    public function getListCount($id)
    {
        $list = $this->getLists($id);

        return $list['SIZE'];
    }

    public function exportList($id)
    {
        try {
            $this->authenticateXml();

            $exportList = json_decode(json_encode($this->silverpop->exportList($id)), true);

            $hash = sha1($exportList['jobId'][0]);

            // cache the result so we can retrieve the file path
            Cache::put($hash, $exportList, 60 * 24);
        } catch (Exception $e) {
            throw $e;
        };

        return $exportList;
    }

    public function addRecipient($listId, $fields, $upsert = true, $autoreply = false, $createdFrom = SilverpopConnector\SilverpopXmlConnector::CREATED_FROM_MANUAL, $lists = [])
    {
        try {
            $this->authenticateXml();

            $contact = json_decode(json_encode($this->silverpop->addRecipient($listId, $fields, $upsert, $autoreply, $createdFrom, $lists)), true);
        } catch (Exception $e) {
            throw $e;
        };

        return $contact[0];
    }

    public function createTemplate($mailingSubject, $mailingBodies, $mailingFromName, $mailingFromEmail, $mailingReplyTo, $listId, $clickThroughs = [])
    {
        $mailingName = $mailingSubject . '-' . time();

        try {
            $this->authenticateXml();

            // public function saveMailing($mailingName, $mailingSubject,
            // $mailingBodies, $mailingFromName, $mailingFromEmail,
            // $mailingReplyTo, $listId, $templateId = null, $saveToSharedFolder
            // = 1, $trackingLevel = 4, $clickThroughs = array())
            $result = json_decode(json_encode($this->silverpop->saveMailing($mailingName, $mailingSubject, $mailingBodies, $mailingFromName, $mailingFromEmail, $mailingReplyTo, $listId, null, 1, 4, $clickThroughs)), true);
        } catch (Exception $e) {
            throw $e;
        };

        return $result;
    }
}
