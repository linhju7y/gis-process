<?php

namespace App\Http\Controllers;

use View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $title = 'Home';
    protected $req = null;
    protected $route = '';

    public function __construct(Request $request)
    {
        $this->req = $request;
        $this->route = env('APP_URL') . $request->getPathInfo();
        View::share('title', $this->title);
    }
}
