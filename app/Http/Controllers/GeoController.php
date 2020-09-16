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

    protected function visualtsss() {
        $file = "Q1_missingtsss.csv";
        $row = 1;
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $num = count($data);
                $row++;
                if (!is_numeric($data[2]) || !is_numeric($data[3])) {
                    continue;
                }
                $lat = floatval($data[2]);
                $lng = floatval($data[3]);
                $query = "SELECT a.id, a.subdivision_id, a.properties, a.wgs84_lat, a.wgs84_lng, a.geojson, UNIX_TIMESTAMP(a.created_at) as created_at, b.label as purpose_label 
                FROM geo_land_item a
                LEFT JOIN geo_land_purpose b ON a.purpose_id = b.id
                WHERE ST_Contains(a.`geometry`, ST_GeomFromText('POINT(" . $lat . " " . $lng . ")'))";
                $query .= " AND a.subdivision_id IN (9289, 9290, 9291, 9292, 9293, 9294, 9295, 9296, 9297, 9298)";
                $dataset = DB::select(DB::raw($query));
                if(count($dataset)) {
                    $datarow = $dataset[0];
                    DB::table("prp_calcfail")->insertGetId([
                        "subdivision_id" => $datarow->subdivision_id,
                        "land_id" => $datarow->id
                    ]);
                }
                echo "<p> $num fields in line $row: <br /></p>\n";
                for ($c=0; $c < $num; $c++) {
                    echo $data[$c] . "<br />\n";
                }
            }
            fclose($handle);
        }
    }
}
