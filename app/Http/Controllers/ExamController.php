<?php

namespace App\Http\Controllers;

use App\Jobs\CadastralJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPImageWorkshop\ImageWorkshop;

class ExamController extends Controller
{

    protected function index()
    {
        ini_set('memory_limit', 0);
        Cache::flush();die;
        $items = scandir(public_path('cadastral'));
        foreach ($items as $item) {
            if ($item == '.' || $item == '..' || $item == '.gitignore') {
                continue;
            }
            $exp = explode(".", $item);
            $ext = $exp[count($exp) - 1];
            if ($ext == 'jpg') {
                $cacheId = "cadastral:flag:" . $item;
                if (Cache::has($cacheId)) {
                    continue;
                }
                Cache::put($cacheId, true, 3600);
                $req = ['path' => public_path('cadastral') . '/' . $item];
                CadastralJob::dispatch($req)->onQueue("cadastral" . rand(1, 4));
            } else if ($ext == 'jgw') {
                unlink(public_path('cadastral') . '/' . $item);
            }
        }
    }

    protected function cadastral()
    {
        $fileKey = ('./d_610152.267591708_1196343.40463893_z.jpg');

        $oX = $oY = $fileType = null;
        $fileName = explode("/", $fileKey);
        $fileName = $fileName[count($fileName) - 1];
        $fileNameArr = explode("_", $fileName);
        $subName = explode(".", $fileNameArr[3]);
        $fileType = $subName[0];
        $oX = $fileNameArr[2];
        $oY = $fileNameArr[1];
        $vn2k = $oY . "," . $oX;
        $cacheId = "cadastral:flag:" . $fileName;
        if (Cache::has($cacheId)) {
            Cache::forget($cacheId);
        }

        $districtId = env('CADASTRAL_DIST', 0);
        echo "Started: " . date("Y-m-d H:i:s") . "<br>";
        if($districtId != 0) {
            $districtId = intval($districtId);
            // $data = DB::table("geo_land_item as a")
            //     ->join("geo_subdivision as b", "a.subdivision_id", "b.id")
            //     ->where("b.parent_id", $districtId)
            //     ->where("a.properties->vn2000", $vn2k)->select("a.*")->first();
            $data = DB::select(DB::raw("select a.* from geo_land_item a join geo_subdivision b on a.subdivision_id = b.id where b.parent_id = " . $districtId . " and JSON_EXTRACT(a.properties, \"$.vn2000\") = '" . $vn2k . "' limit 1"));
            if(isset($data) && count($data) > 0) {
                $data = $data[0];
            } else $data = null;
        } else {
            $data = DB::table("geo_land_item")
                ->where("properties->vn2000", $vn2k)->first();
        }
        $subdivision = isset($data) && isset($data->subdivision_id) ? $data->subdivision_id : 0;
        if ($subdivision == 0) {
            if (copy($fileKey, public_path('cadastral_not_found') . '/' . $fileName) && file_exists($fileKey)) {
                unlink($fileKey);
            }
            throw new \Exception('subdivision_not_found');
        }
        $wgs84Lat = $data->wgs84_lat ?? $oX;
        $wgs84Lng = $data->wgs84_lng ?? $oY;
        $saveName = ['d', $wgs84Lat, $wgs84Lng, trim(strtolower($fileType)) . '.jpg'];
        $saveName = implode('_', $saveName);

        $width = 1200;
        $height = 800;
        if (trim(strtolower($fileType)) == 'o') {
            $width = 1600;
            $height = 800;
        }

        $background = 'FFFFFF';

        $image = ImageWorkshop::initVirginLayer($width, $height, $background);
        $photo = ImageWorkshop::initFromPath($fileKey);
        $pW = $photo->getWidth();
        $pH = $photo->getHeight();
        $mW = ($width / 2) - ($pW / 2);
        $mW = $mW < 0 ? 0 : $mW;
        $mH = ($height / 2) - ($pH / 2);
        $mH = $mH < 0 ? 0 : $mH;
        $image->addLayerOnTop($photo, $mW, $mH, "lt");
        $watermark = ImageWorkshop::initFromPath(public_path("img/watermark.png"));
        $image->addLayerOnTop($watermark, 0, 0, "lt");
        
        $dirPath = public_path("results") . '/' . $subdivision;
        if(!(file_exists($dirPath) && is_dir($dirPath))) {
            mkdir($dirPath);
        }
        $image->save($dirPath, $saveName, true);


        // $tempFile = tempnam(sys_get_temp_dir(), '');
        // imagejpeg($image->getResult(), $tempFile, 100);
        // $s3url = "/" . $subdivision . "/" . $saveName;
        // $res = Storage::disk('s3')->put('cadastral' . $s3url, file_get_contents($tempFile), 'public');
        if (file_exists($fileKey)) {
            unlink($fileKey);
        }
        // if ($res && file_exists($tempFile)) {
        //     unlink($tempFile);
        // }
    }

    protected function rotatewgs84() {
        $fileKey = public_path("rotate_input/d_106.694_10.79141_h.jpg");
        if(!file_exists($fileKey)) return;
        $fileName = explode("/", $fileKey);
        $fileName = $fileName[count($fileName) - 1];
        $fileNameArr = explode("_", $fileName);
        dd($fileNameArr);
    }

    protected function rotatewgs84image() {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', 0);
        $ids = DB::select(DB::raw("SELECT a.id, a.wgs84_lat, a.wgs84_lng FROM geo_land_item a
            JOIN geo_subdivision b ON a.subdivision_id = b.id
            WHERE b.parent_id IN (9299) 
            AND a.wgs84_lat > 100"));// 9288,9527,9299
        foreach($ids as $i) {
            $wgs84_lat = $i->wgs84_lng;
            $wgs84_lng = $i->wgs84_lat;
            $wgs84 = "POINT(" . $wgs84_lat . " " . $wgs84_lng . ")";
            DB::table("geo_land_item")
                ->where("id", $i->id)
                ->update([
                    'wgs84' => DB::raw("ST_GeomFromText('" . $wgs84 . "')"),
                    'wgs84_lat' => $wgs84_lat,
                    'wgs84_lng' => $wgs84_lng
                ]);
        }
        dd(time());
    }
}
