<?php

namespace App\Http\Controllers;

use App\Support\ApiResponder;

abstract class Controller
{
    protected ApiResponder $responder;

    public function __construct()
    {
        $this->responder = app(ApiResponder::class);
    }
}
