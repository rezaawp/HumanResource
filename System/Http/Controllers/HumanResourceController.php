<?php

namespace App\Extensions\HumanResource\System\Http\Controllers;

use App\Http\Controllers\Controller;

class HumanResourceController extends Controller {
    function index() {
        return view("human-resource::welcome");
    }
}