<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Jobs\CadastralJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CadastralCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cadastral:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan all images in Cadastral directory';

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
        $items = scandir(public_path('cadastral'));
        foreach ($items as $item) {
            if (in_array($item, ['.', '..', '.gitignore'])) {
                continue;
            }
            $exp = explode(".", $item);
            $ext = $exp[count($exp) - 1];
            if ($ext == 'jpg') {
                $cacheId = "cadastral:flag:" . $item;
                if (Cache::has($cacheId)) {
                    continue;
                }
                Cache::put($cacheId, true, 36000);
                $req = ['path' => public_path('cadastral') . '/' . $item];
                CadastralJob::dispatch($req)->onQueue("cadastral" . rand(1, env('CADASTRAL_QUEUE', 12)));
            } else if ($ext == 'jgw') {
                unlink(public_path('cadastral') . '/' . $item);
            }
        }
    }
}
