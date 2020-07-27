<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{

    protected function index()
    {
    }

    protected function getSubdivision($id = null)
    {
        if (intval($id) == 0) $id = null;

        $dataset = DB::table("geo_subdivision")->where('parent_id', $id)
            ->where("status", 1)
            ->select("id", "parent_id", "government_id", "government_label", "label", "created_at")
            ->orderBy("sortorder", "asc")
            ->get()->toArray();
        return response()->json([
            "status" => true,
            "data" => $dataset
        ]);
    }
}
