<?php

namespace App\Http\Controllers;

use App\Jobs\CadastralJob;
use App\Jobs\HcmgisJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPImageWorkshop\ImageWorkshop;
use App\Libraries\RestClient;
use Carbon\Carbon;

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

    protected function hcmgis() {
        ini_set('max_execution_time', 0);
        $lst = [
            "baselayer_chungcu" => "hcm_rt_chung_cu",///
            "tt" => "hcm_tdtt",///
            "giaothong_line" => "hcm_giao_thong_duong_bo",///
            "cosoxquang" => "hcm_xquang",///
            "thuyhe_polygon_1" => "hcm_giao_thong_duong_thuy",///
            "pgd" => "hcm_pgd_ngan_hang",///
            "hethongcapcuu" => "hcm_he_thong_cap_cuu",///
            "atm_1" => "hcm_atm",///
            "cosoonhiem" => "hcm_o_nhiem",///
            "sonha_quan11_2019" => "hcm_so_nha_q11",///
            "sonha_q7_2019" => "hcm_so_nha_q7",///
            "sonha_govap_2019" => "hcm_so_nha_govap",///
            "sonha_binhtan_2019" => "hcm_so_nha_binhtan",///
            "sn_binhthanh_2019" => "hcm_so_nha_binhthanh",///
            "sncn_11102018_q09_merge" => "hcm_so_nha_q9",///
            "sncn_11102018_qtd_merge" => "hcm_so_nha_thuduc",///
            "sncn_09102018_q02_merge" => "hcm_so_nha_q2",///
            "sncn_09102018_qtb_merge" => "hcm_so_nha_tanbinh",///
            "sncn_08102018_cc" => "hcm_so_nha_cuchi",///
            "sncn_08102018_qtp" => "hcm_so_nha_tanphu",///
            "rt_quan5" => "hcm_so_nha_q5",///
            "rt_quan4" => "hcm_so_nha_q4",///
            "rt_quan3" => "hcm_so_nha_q3",///
            "rt_quan1" => "hcm_so_nha_q1",///
            "sncn_08102018_q12" => "hcm_so_nha_q12",///
            "rt_quan10" => "hcm_so_nha_q10",///
            "rt_quanphunhuan_1" => "hcm_so_nha_phunhuan",///
            "dansoquanhuyen" => "hcm_danso_quanhuyen",///
            "nhathuoc_2017_wgs84" => "hcm_nhathuoc",///
            "coso_yte_2017_wgs84" => "hcm_csyt",///
            "khachsan_wgs84_1" => "hcm_khach_san",///
            "timduonggiaothong_wgs84" => "hcm_timduong",///
            "tokhupho_wgs84" => "hcm_ranhto_khupho",// - 10p chua xong
            "dansophuongxa_wgs84" => "hcm_danso_phuongxa",///
            "cosogiaoduc_wgs84" => "hcm_giaoduc",/// 
            "nhahang_wgs84" => "hcm_nhahang",///
            "tram_1" => "hcm_tram_bus",///
            "cauduong_1" => "hcm_cauduong",///
            "tuyenxebus" => "hcm_tuyen_bus",///
            "trungtamthuongmai" => "hcm_tttm",///
            "duongsat" => "hcm_duong_sat",///
            "tramxebus" => "hcm_tram_bus_osm",///
            "metro_1" => "hcm_metro",///
            "tramxangdau" => "hcm_tram_xangdau",///
            "cho_1" => "hcm_market",///
            "cayxanh_pn_1" => "hcm_cx_phunhuan",///
            "cayxanh_q11_1" => "hcm_cx_q11",///
            "cayxanh_q10_1" => "hcm_cx_q10",///
            "cayxanh_q3_1" => "hcm_cx_q3",///
            "cayxanh_q5_1" => "hcm_cx_q5",///
            "cayxanh_bt_1" => "hcm_cx_binhthanh",///
            "uybannhandan" => "hcm_ubnd",///
            "truden_q5_1" => "hcm_truden_q5",///
            "tudieukhien_q5_1" => "hcm_tudieukhien_q5",///
            "cum_cong_nghiep" => "hcm_cum_cong_nghiep",///
            "ranhthua7" => "hcm_rt_q7",///
            "ca_px" => "hcm_ca_phuongxa",///
            "ranhthuahocmon" => "hcm_rt_hocmon",// - 2 lan chay (30p) ko dc
            "ranhthua11" => "hcm_rt_q11",///
            "cx" => "hcm_cx_vvk_mct",
            "ranhthuagv" => "hcm_rt_govap",///
            "quan" => "hcm_ranhquan",///
            "ranhthua8" => "hcm_rt_q8",///
            "ranhphuong_1" => "hcm_ranhphuong",///
            "atm" => "hcm_atm_scongthuong",///
            "quan6" => "hcm_rt_q6",///
            "quanbinhthanh" => "hcm_rt_binhthanh",///
            "quanbinhtan" => "hcm_rt_binhtan",///
            "quanbinhchanh" => "hcm_rt_binhchanh",
            "huyennhabe" => "hcm_rt_nhabe",///
            "huyencangio" => "hcm_rt_cangio",///
            "thuyhe_line" => "hcm_timsong",///
            "viahe" => "hcm_viahe",///
            "cayxanh_q1" => "hcm_cx_q1",///
        ];

        // foreach($lst as $k => $v) {
        //     echo $k . '<br>';
        //     $req = [
        //         'code' => $k, 
        //         'file' => $v,
        //         'index' => 1];
            
        //     HcmgisJob::dispatch($req)->onQueue("hcmgis");
        // }

        $this->req = [
            'code' => 'quanbinhchanh',
            'file' => 'hcm_rt_binhchanh',
            'index' => 00001
        ];
        $file_num = 1;

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
                $str = $res->response;
                file_put_contents(public_path("hcmgis") . '/' . $this->req['file'] . "_" . $file_num . ".json", $str);
                $resp = json_decode($str);
                // $totalRows = $resp->totalFeatures ?? 0;
                // $times = ceil($totalRows / $items);
                // if($times > 1) {
                //     for($i = 1; $i < $times; $i++) {
                //         $newItems = ($items * $i) + 1;
                //         $req = [
                //             'code' => $k, 
                //             'file' => $v,
                //             'index' => $newItems];
                //         HcmgisJob::dispatch($req)->onQueue("hcmgis");
                //     }
                // }
                dd($this->req['code'], $this->req['file'], $resp->totalFeatures, $this->req['index']);
                break;
            }
        }

        // $filename = "hcm_so_nha_q11";
        // $items = 40000;

        // $url = "http://portal.hcmgis.vn/geoserver/wfs?";
        // $params = [
        //     "srsName" => "EPSG:4326",
        //     "typename" => "geonode:" . "sonha_quan11_2019",
        //     "maxFeatures" => $items,
        //     "startIndex" => "1",
        //     "outputFormat" => "json",
        //     "version" => "1.0.0",
        //     "service" => "WFS",
        //     "request" => "GetFeature"
        // ];
        // $prm = [];
        // $cl = new RestClient();
        // foreach($params as $k => $v) {
        //     $prm[] = $k . '=' . $v;
        // }
        // $urlTemp = $url . implode("&", $prm);

        // for($j = 0; $j < 500; $j++) {
        //     $res = $cl->get($urlTemp);
        //     if(strpos($res->response, '<?xml version="1.0" ') === false) {
        //         $str = $res->response;
        //         $resp = json_decode($str);
        //         $totalRows = $resp->totalFeatures ?? 0;
        //         $times = ceil($totalRows / $items);
        //         file_put_contents("hcmgis/" . $filename . ".json", $str);
        //         dd($times);
        //         break;
        //     }
        // }
        // dd(1);
    }

    protected function street() {
        ini_set("max_execution_time", 3600);
        $const = [
            '9288' => 'quan_01',
            '9420' => 'quan_02',
            '9432' => 'quan_03',
            '9480' => 'quan_04',
            '9496' => 'quan_05',
            '9512' => 'quan_06',
            '9555' => 'quan_07',
            '9527' => 'quan_08',
            '9324' => 'quan_09',
            '9447' => 'quan_10',
            '9463' => 'quan_11',
            '9299' => 'quan_12',
            '9544' => 'quan_binh_tan',
            '9355' => 'quan_binh_thanh',
            '9338' => 'quan_go_vap',
            '9404' => 'quan_phu_nhuan',
            '9376' => 'quan_tan_binh',
            '9392' => 'quan_tan_phu',
            '9311' => 'quan_thu_duc',
            '9601' => 'huyen_binh_chanh',
            '9626' => 'huyen_can_gio',
            '9566' => 'huyen_cu_chi',
            '9588' => 'huyen_hoc_mon',
            '9618' => 'huyen_nha_be',
        ];
        $filePath = public_path("street/ho_chi_minh/{{value}}.json");
        $echo = "Start: " . date("Y-m-d H:i:s") . "<br/>";
        foreach($const as $key => $val) {
            $data = file_get_contents(str_replace("{{value}}", $val, $filePath));
            $data = json_decode($data, JSON_UNESCAPED_UNICODE);
            $datasource = $data['features'];
            $duong = [];
            $hem = [];
            $koxacdinh = [];
            foreach($datasource as $v) {
                $name = strtolower($v['attributes']['name']);
                if(strlen(trim($name)) <= 0) {
                    $koxacdinh[] = $v;
                } else if(strpos($name, "hẻm") !== false) {
                    $hem[] = $v;
                } else {
                    $duong[] = $v;
                }
            }

            $insdataset = [];
            foreach($duong as $v) {
                $attr = $v['attributes'];
                $coordinates = $v['geometry']['paths'];
                $polyline = "";
                foreach ($coordinates as $i) {
                    $tmp = [];
                    foreach ($i as $j) {
                        $tmp[] = "(" . $j[1] . "," . $j[0] . ")";
                    }
                    $polyline = "(" . implode(",", $tmp) . ")";
                }
                $stname = str_replace("Đường", "", $attr['name']);
                $insdata = [
                    'subdivision_id' => intval($key),
                    'category_id' => 4,
                    'surface_id' => 1,
                    'street_name' => trim($stname),
                    'keyword_tags' => '',
                    'maxspeed' => intval($attr['maxspeed']),
                    'oneway' => null,
                    'wgs84_polyline' => DB::raw("'$polyline'"),
                    // 'wgs84_polyline' => DB::raw("public.ST_GeomFromText('$polyline')"),
                    'properties' => json_encode([
                        'osm' => [
                            'osm_id' => $attr['osm_id'],
                            'code' => $attr['oneway'],
                            'fclass' => $attr['fclass'],
                            'layer' => $attr['layer'],
                            'bridge' => $attr['bridge'] == 'F' ? false : true,
                            'tunnel' => $attr['tunnel'] == 'F' ? false : true,
                        ]
                    ]),
                    'status' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ];
                $insdataset[] = $insdata;
            }
            $echo .= "Insert: " . date("Y-m-d H:i:s") . "<br/>";
            $ins = array_chunk($insdataset, 200);
            foreach ($ins as $v) {
                DB::connection("pgsql")->table("geo_street_tmp")->insert($v);
            }
            $echo .= "Done: " . date("Y-m-d H:i:s") . "<br/>";
        }
        dd($echo);
        $data = file_get_contents($filePath);
        $data = json_decode($data, JSON_UNESCAPED_UNICODE);
        $datasource = $data['features'];
        // dd($datasource[1]); 
        $duong = [];
        $hem = [];
        $koxacdinh = [];
        foreach($datasource as $v) {
            $name = strtolower($v['attributes']['name']);
            if(strlen(trim($name)) <= 0) {
                $koxacdinh[] = $v;
            } else if(strpos($name, "hẻm") !== false) {
                $hem[] = $v;
            } else {
                $duong[] = $v;
            }
        }   
        dd(array_chunk($duong, 100), array_chunk($hem, 100)[0], array_chunk($koxacdinh, 100));
    }

    protected function street111() {
        ini_set('memory_limit', '1024M');
        $filePath = public_path("abc.sql");
        $data = file_get_contents($filePath);
        $data = explode("\r\n", $data);
        $pre = "INSERT INTO geo_subdivision_border (subdivision_id, wgs84_polygon, status, created_at, updated_at) VALUES (";
        $output = [];
        foreach($data as $k => $v) {
            $row = str_replace("INSERT INTO geo_subdivision_border (subdivision_id, wgs84, wgs84_lat, wgs84_lng, wgs84_polygon, status, created_at, updated_at) VALUES (", "", $v);
            $row = substr($row, 0, strlen($row) - 2);
            $row = explode(", ", $row);
            if(count($row) <= 1) continue;
            $op = [];
            $ins = [];
            $op[] = $row[0];
            $ins['subdivision_id'] = $row[0];
            // $tmp1 = str_replace("('(", "", $row[1]);
            // $tmp1 = str_replace(")')", "", $tmp1);
            // $op[] = "'(" . str_replace(" ", ",", $tmp1) . ")'";
            // $op[] = doubleval($row[2]);
            // $op[] = doubleval($row[3]);
            $polygon = [];
            $ispoly = false;
            foreach($row as $kr => $r) {
                if(strpos($r, "('((") !== false) {
                    $ispoly = true;
                }
                if($ispoly) {
                    $tmp = str_replace("('((", "", $r);
                    $tmp = str_replace("))')", "", $tmp);
                    $polygon[] ="(" . str_replace(" ", ",", $tmp) . ")";
                }
                if(strpos($r, "))')") !== false) {
                    $ispoly = false;
                    break;
                }
            }
            $tmpo = "'(" . implode(",", $polygon) . ")'";
            $tmpo1 = "ST_GeomFromText('POLYGON(" . implode(",", $polygon) . ")')";
            $op[] = $tmpo !== "'()'" ? $tmpo : "NULL";
            $ins['wgs84_polygon'] = $tmpo1 !== "ST_GeomFromText('POLYGON()')" ? DB::raw($tmpo1) : "NULL";
            $op[] = $row[count($row) - 3];
            $ins['status'] = $row[count($row) - 3];
            $op[] = $row[count($row) - 2];
            $ins['created_at'] = $row[count($row) - 2];
            $op[] = $row[count($row) - 1];
            $ins['updated_at'] = $row[count($row) - 1];
            // dd($ins);
            // dd($pre . implode(", ", $op) . ");\r\n");
            // DB::connection("pgsql")->table("geo_subdivision_border")->insert($ins);
            $output[] = $pre . implode(", ", $op) . ");\r\n";
        }
        $output = array_chunk($output, 50);
        foreach($output as $k => $v) {
            file_put_contents("abc_out_" . $k . ".sql", $v);
        }
        dd(time());
    }
}
