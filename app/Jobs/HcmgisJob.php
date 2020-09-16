<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Libraries\RestClient;
use Illuminate\Support\Facades\Log;

class HcmgisJob implements ShouldQueue
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
        // return;
        Log::info($this->req['code'] . " " . $this->req['index'] . " " . date("Y-m-d H:i:s"));
        $items = 20000;
        $url = "http://portal.hcmgis.vn/geoserver/wfs?";
        $params = [
            "srsName" => "EPSG:4326",
            "typename" => "geonode:" . $this->req['code'],
            "maxFeatures" => $items,
            "startIndex" => $this->req['index'],
            "outputFormat" => "json",
            "version" => "1.0.0",
            "service" => "WFS",
            "request" => "GetFeature"
        ];

        $prm = [];
        $cl = new RestClient();
        foreach($params as $k => $v) {
            $prm[] = $k . '=' . $v;
        }
        $urlTemp = $url . implode("&", $prm);
        
        for($j = 0; $j < 500; $j++) {
            $res = $cl->get($urlTemp);
            if(strpos($res->response, '<?xml version="1.0" ?>') === false) {
                Log::info($this->req['code'] . " " . date("Y-m-d H:i:s"));
                $str = $res->response;
                file_put_contents(public_path("hcmgis") . '/' . $this->req['file'] . "_" . ($this->req['index'] % $items) . ".json", $str);
                $resp = json_decode($str);
                $totalRows = $resp->totalFeatures ?? 0;
                $times = ceil($totalRows / $items);
                if($times > 1) {
                    for($i = 1; $i < $times; $i++) {
                        $newItems = ($items * $i) + 1;
                        $req = [
                            'code' => $k, 
                            'file' => $v,
                            'index' => $newItems];
                        HcmgisJob::dispatch($req)->onQueue("hcmgis");
                    }
                }
                break;
            }
        }
    }
}