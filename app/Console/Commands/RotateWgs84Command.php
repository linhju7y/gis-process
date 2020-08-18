<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Jobs\RotateWgs84Job;
use Illuminate\Support\Facades\Cache;

class RotateWgs84Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rotatewgs84:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan all images in RotateInput directory';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $items = scandir(public_path('rotate_input'));
        foreach ($items as $item) {
            if (in_array($item, ['.', '..', '.gitignore'])) {
                continue;
            }
            $exp = explode(".", $item);
            $ext = $exp[count($exp) - 1];
            if ($ext == 'jpg') {
                $cacheId = "rotatewgs84:flag:" . $item;
                if (Cache::has($cacheId)) {
                    continue;
                }
                Cache::put($cacheId, true, 3600);
                $req = ['path' => public_path('rotate_input') . '/' . $item];
                RotateWgs84Job::dispatch($req)->onQueue("rotatewgs84" . rand(1, 1));
            }
        }
    }
}
