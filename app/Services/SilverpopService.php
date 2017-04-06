<?php

namespace App\Services;

use Exception;

use Cache;

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
}
