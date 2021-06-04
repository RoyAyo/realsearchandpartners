<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Auth;

class Controller extends BaseController
{
    public function respondWithToken($token)
    {
        return response()->json([
            'token' => $token,
            'data' => Auth::user(),
        ]);
    }

    public function returnError($error, $status = 404)
    {
        return response()->json([
            'success' => false,
            'msg' => $error,
        ], $status );
    }
}