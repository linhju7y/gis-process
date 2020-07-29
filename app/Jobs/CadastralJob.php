<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPImageWorkshop\ImageWorkshop;

class CadastralJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    protected $req;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->req = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $fileKey = $this->req['path'];
        if(!file_exists($fileKey)) return;

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

        $data = DB::table("geo_land_item")->where("properties->vn2000", $vn2k)->first();
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
}
