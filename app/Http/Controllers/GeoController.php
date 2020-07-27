<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class GeoController extends Controller
{

    protected function index()
    {
    }

    protected function land()
    {
        if (request()->isMethod('post')) {
            $subdivision = intval($_REQUEST['level4'] ?? 0);
            if ($subdivision === 0) die("Subdivision not found");

            $sub4 = $subdivision;
            $sub3 = DB::table("geo_subdivision")->where("id", $sub4)->select("parent_id")->first()->parent_id;
            $sub2 = DB::table("geo_subdivision")->where("id", $sub3)->select("parent_id")->first()->parent_id;
            $sub1 = DB::table("geo_subdivision")->where("id", $sub2)->select("parent_id")->first()->parent_id;
            $subKey = implode("_", [time(), $sub1, $sub2, $sub3, $sub4]);

            $file = $_FILES['file'];
            $data = file_get_contents($file['tmp_name']);

            $filePath = "../storage/geos";
            if (!file_exists($filePath)) {
                mkdir($filePath);
            }
            $fw = @fopen($filePath . DIRECTORY_SEPARATOR . $subKey . ".json", "w");
            if ($fw != false) {
                fwrite($fw, $data);
                fclose($fw);
            }
            $data = json_decode($data);
            // dd($file, $data->features[0]);

            // get_mdsdd
            $mdsdd = null;
            if (Cache::has("mdsdd")) {
                $mdsdd = Cache::get("mdsdd");
            } else {
                $mdsdd = DB::table("geo_land_purpose")->where("status", 1)
                    ->select("id", "code", "label")->get()->toArray();
                Cache::put("mdsdd", $mdsdd, 3600);
            }

            $echo = "Start: " . date("Y-m-d H:i:s") . "<br/>";
            $fileId = DB::table("geo_land_file")->insertGetId([
                "subdivision_id" => $sub4,
                "unique_key" => $subKey,
                "file_name" => $filePath . DIRECTORY_SEPARATOR . $subKey . ".json",
                "properties" => json_encode($file),
                "status" => 1
            ]);
            $land = $data->features;
            $insdata = [];
            foreach ($land as $k => $i) {
                // try {
                if (isset($i->geometry->type) && $i->geometry->type == "MultiPolygon") continue;
                $props = $i->properties ?? $i->attributes;
                $wgs84 = explode(',', $props->wgs84);
                $lat = $wgs84[0];
                $lng = $wgs84[1];
                $point = "POINT(" . $lat . " " . $lng . ")";
                $polygon = [];
                try {
                    $coordinates = $i->geometry->coordinates ?? $i->geometry->rings;
                } catch (\Exception $e) {
                    dd($k, $i, $props);
                }
                foreach ($coordinates as $key => $val) {
                    $tmp = [];
                    foreach ($val as $k => $v) {
                        $tmp[] = $v[1] . " " . $v[0];
                    }
                    $polygon[] = "(" . implode(",", $tmp) . ")";
                }
                $polygon = "POLYGON(" . implode(",", $polygon) . ")";
                $mdsddId = null;
                foreach ($mdsdd as $v) {
                    if ($v->code == strtolower($props->mdsdd)) {
                        $mdsddId = $v->id;
                    }
                }

                $insdata[] = [
                    "subdivision_id" => $sub4,
                    "purpose_id" => $mdsddId,
                    "file_id" => $fileId,
                    "sheet_id" => $props->so_to_ban_do,
                    "parcel_id" => $props->so_hieu_thua,
                    "address" => "",
                    "geometry" => DB::raw("ST_GeomFromText('" . $polygon . "')"),
                    "wgs84" => DB::raw("ST_GeomFromText('" . $point . "')"),
                    "wgs84_lat" => $lat,
                    "wgs84_lng" => $lng,
                    "geojson" => json_encode($i->geometry),
                    "properties" => json_encode($props),
                    "status" => 1
                ];
                // } catch(\Exception $e) {
                //     echo $k;
                // }
            }
            $echo .= "Insert: " . date("Y-m-d H:i:s") . "<br/>";
            $ins = array_chunk($insdata, 1000);
            foreach ($ins as $v) {
                DB::table("geo_land_item")->insert($v);
            }
            $echo .= "Done: " . date("Y-m-d H:i:s") . "<br/>";
        }
        return view("geo.land", ['echo' => ($echo ?? "")]);
    }

    protected function flushland()
    {
        if (request()->isMethod('post')) {
            $subdivision = intval($_REQUEST['level4'] ?? 0);
            if ($subdivision === 0) die("Subdivision not found");

            $echo = "Start: " . date("Y-m-d H:i:s") . "<br/>";
            DB::table('geo_land_item')->where('subdivision_id', $subdivision)->delete();
            $echo .= "Done: " . date("Y-m-d H:i:s") . "<br/>";
        }
        return view("geo.flushland", ['echo' => ($echo ?? "")]);
    }
}
