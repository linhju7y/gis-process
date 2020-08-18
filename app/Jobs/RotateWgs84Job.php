<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Cache;

class RotateWgs84Job implements ShouldQueue
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
        $fileName = explode("/", $fileKey);
        $fileName = $fileName[count($fileName) - 1];
        $fileNameArr = explode("_", $fileName);
        $newFileName = [
            $fileNameArr[0],
            $fileNameArr[2],
            $fileNameArr[1],
            $fileNameArr[3]
        ];
        $newFileName = implode("_", $newFileName);

        $cacheId = "rotatewgs84:flag:" . $fileName;
        if (Cache::has($cacheId)) {
            Cache::forget($cacheId);
        }
        rename($fileKey, public_path('rotate_output') . '/' . $newFileName);
    }
}