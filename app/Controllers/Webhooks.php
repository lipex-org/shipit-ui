<?php

namespace App\Controllers;

class Webhooks extends BaseController
{
    public function trigger(string $token)
    {
        $api = new Api();
        $api->initController($this->request, $this->response, $this->logger);
        return $api->webhook($token);
    }
}
