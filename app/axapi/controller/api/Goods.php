<?php

// +----------------------------------------------------------------------
// | Shop-Demo for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023 Anyon <zoujingli@qq.com>
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 会员免费 ( https://thinkadmin.top/vip-introduce )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\axapi\controller\api;

use app\axapi\model\ShopGoods;
use app\axapi\model\ShopData;

use app\axapi\model\ShopGoodsCate;
use app\axapi\model\ShopGoodsMark;
use app\axapi\service\ExpressService;
use think\admin\Controller;


use app\axapi\service\UserTokenService;

use app\axapi\model\DataUserMyCollect;

use think\facade\Db;
use app\validate\JsonValidate;

use GatewayClient\Gateway;

use think\facade\Cache;

/**
 * 商品数据接口
 * Class Goods
 * @package app\axapi\controller\api
 */
class Goods extends Controller
{

    protected function initialize()
    {
        $this->page = 10;
        Gateway::$registerAddress = '127.0.0.1:1238';
    }
    private function shijian($m)
    {
    }
    public function goodTimeSearch()
    {
        $user = $this->isuser();
        if($user[0] == 1 or 1==1){
            $data = $this->_vali([
                'type.require' => '请选择时间',
                'pid.require' => '请选择产品'
            ]);
            $datalist = Db::table('shop_data')
                ->field('id,name,media_id,date,time,open,close,height,low')
                ->where('media_id',$data['pid'])->order('id desc');
            switch ($data['type']) {
                case 1:
                    // 1m
                    $datalist = $datalist->paginate(20);
                    break;
                case 2:
                    // 5m
                    $datalist = $datalist->whereIn('i','05,10,15,20,25,30,35,40,45,50,55,00')->paginate(20);
                    break;
                case 3:
                    // 15m
                    $datalist = $datalist->whereIn('i','15,30,45,00')->paginate(20);
                    break;
                case 4:
                    // 30m
                    $datalist = $datalist->whereIn('i','15,30,45,00')->paginate(20);
                    break;
                // 可以有更多的 case
                default:
                    // 默认情况下执行的代码块
                    $datalist = [];
                    die;
            }
            $this->success($datalist);
        }
    }
    public function goodTimeClass()
    {
        $data = [
            [
                'value'=>1,
                'label'=>'M1'
            ],
            [
                'value'=>2,
                'label'=>'M5'
            ],
            [
                'value'=>3,
                'label'=>'M15'
            ],
            [
                'value'=>4,
                'label'=>'M30'
            ],
            [
                'value'=>5,
                'label'=>'H1'
            ],
            [
                'value'=>6,
                'label'=>'H4'
            ],
            [
                'value'=>7,
                'label'=>'D1'
            ],
            [
                'value'=>8,
                'label'=>'W1'
            ],
            [
                'value'=>9,
                'label'=>'MN'
            ]
        ];
        return $this->success('请求成功',$data);
    }
    
    //生成数据
    public function shopData()
    {
        $title = '生成数据';
        $command = 'xdata:ShopData';
        $code = sysqueue($title, $command, $later = 0, $data = [], $rscript = 1, $loops = 1);
        var_dump($code);
    }

    /**
     * @return void
     * 同步历史记录，按开始结束时间
     */
    private function tongbu()
    {

        // 历史_按开始结束时间v2
        function getdata($item,$code)
        {
            $yesterday = strtotime('yesterday');
//            dump(strtotime(date('Y-m-d 23:59:59', $yesterday)));

            // 如果不是周六周日 每分钟更新一次

            // 历史_按开始结束时间v2
            if (date('w') != 6 && date('w') != 0)
            {
                $a = date('Y-m-d H:i:s', time());
                $b = date('Y-m-d H:i:s', time() - 490 * 60);
//                $a = date('Y-m-d H:i:s',$yesterday);
//                $b = date('Y-m-d H:i:s', $yesterday - 490 * 60);

                $a = urlencode($a);
                $b = urlencode($b);


                $host = "http://alirmcom2.market.alicloudapi.com";
                $path = "/query/comkm2v2";
                $method = "GET";
                $appcode = $code['value'];
                $headers = array();
                array_push($headers, "Authorization:APPCODE " . $appcode);
                $querys = "dateed={$a}&datest={$b}&period=1M&symbol={$item['num_code']}&withlast=1";
                $bodys = "";
                $url = $host . $path . "?" . $querys;

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_FAILONERROR, false);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HEADER, false);
                if (1 == strpos("$" . $host, "https://")) {
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                }
                $data = curl_exec($curl);

//                Cache::set('curldata' . $item['num_code'], $data, 0);
//                $data = Cache::get('curldata' . $item['num_code']);


                $data = (json_decode($data, true)['Obj']);
                dump($data);
                $data = (explode(';', $data));
                if ($data[0] != '') {
                    foreach ($data as $datum) {
                        $d = explode(',', $datum);
                        //            dump(date('Y-m-d H:i:s',$d[0]));
                        // 收 开 高 低
                        //            dump($d[1],$d[2],$d[3],$d[4]);
                        $ins_date = [
                            'media_id' => $item['id'],
                            'y' => date('Y', $d[0]),
                            'm' => date('m', $d[0]),
                            'd' => date('d', $d[0]),
                            'h' => date('H', $d[0]),
                            'i' => date('i', $d[0]),
                            's' => date('s', $d[0]),
                            'w' => date('w', $d[0]),
                            'date' => date('Y-m-d H:i:s', $d[0]),
                            'time' => $d[0],
                            'name' => $item['name'],
                            'close' => $d[1],
                            'open' => $d[2],
                            'height' => $d[3],
                            'low' => $d[4],
                        ];

                        $m = ShopData::where('time', $d[0])->where('name', $item['name'])->find();
                        if ($m == NULL) {
                            $m = ShopData::insert($ins_date);
                            if ($m) {
                                dump($m . '新增成功');
                            }
                        }
                    }
                }
            }
        }

        // 按分页查询
        function getdatapage($item,$code)
        {
            if (1 == 2) {
                $host = "http://alirmcom2.market.alicloudapi.com";
                $path = "/query/comkmv2";
                $method = "GET";
                $appcode = $code['value'];
                $headers = array();
                array_push($headers, "Authorization:APPCODE " . $appcode);
                $querys = "period=1M&pidx=1&psize=100&symbol={$item['num_code']}&withlast=1";
                $bodys = "";
                $url = $host . $path . "?" . $querys;

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_FAILONERROR, false);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HEADER, false);
                if (1 == strpos("$" . $host, "https://")) {
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                }
                $curlData = (curl_exec($curl));
                Cache::set('curldatapage' . $item['num_code'], $curlData, 0);
            }
            $data = Cache::get('curldatapage'.$item['num_code']);
            $data = (json_decode($data, true)['Obj']);
            $data = (explode(';', $data));
            foreach ($data as $datum) {
                $d = explode(',', $datum);
                $ins_date = [
                    'media_id' => $item['id'],
                    'y' => date('Y', $d[0]),
                    'm' => date('m', $d[0]),
                    'd' => date('d', $d[0]),
                    'h' => date('H', $d[0]),
                    'i' => date('i', $d[0]),
                    's' => date('s', $d[0]),
                    'w' => date('w', $d[0]),
                    'date' => date('Y-m-d H:i:s', $d[0]),
                    'time' => $d[0],
                    'name' => $item['name'],
                    'close' => $d[1],
                    'open' => $d[2],
                    'height' => $d[3],
                    'low' => $d[4],
                ];
                $m = ShopData::where('time', $d[0])->where('name', $item['name'])->find();
                if ($m == NULL) {
                    $m = ShopData::insert($ins_date);
                    if ($m) {
                        dump($m . '新增成功');
                    }
                }
            }

        }

        $code = Db::table('system_data')->where('name', 'code')->cache(true, 60000)->find();
        // 查询有效产品数据
        $goodsList = Db::table('shop_goods')
            ->field('id,name,num_code,isopen,status')
            ->where('deleted',0)
            ->where('status',1)
            ->cache(true,60000)->select()->toArray();

        foreach ($goodsList as $item)
        {
//            $data = getdata($item,$code);
            $data = getdatapage($item,$code);
        }
    }
    /**
     * @return void
     * 定时任务_发送数据 SendMinuteMsgSend
     * 定时任务__每分钟生成数据
     */
    public function sendMsg_m()
    {
        /*
        $d = 7;
        $data = ShopData::where('media_id',$d)->order('time asc')->select();
        $this->success($d,$data);
        */
        $this->tongbu();die;
        $this->sendMsg_m1(1);
    }
    public function sendMsg_m1($t=1)
    {
        if($t ==9){
            $title = '发送消息60s';
            $command = 'xdata:SendMinuteMsg';
            $code = sysqueue($title, $command, $later = 0, $data = [], $rscript = 0, $loops = 59);
            var_dump($code);
            die;
        }
        // 开始请求 获取code
        $code = Cache::get('code_value');
        if(empty($code)){
            $code = Db::table('system_data')->where('name','code')->cache(true,60000)->find()['value'];
            Cache::set('code_value',$code,0);
        }
        $code = Cache::get('code_value');

        // 获取产品
        $goodsList = Db::table('shop_goods')
            ->field('id,name,num_code,isopen,status')
            ->where('deleted',0)
            ->cache(true,60000)->select()->toArray();
        Cache::set('goods_data',$goodsList);
        $senddata1 = [];
        $a = 0;
        foreach ($goodsList as $item) {
            $a++;
            echo "请求{$a}次";
            // 如果不是周六周日 每分钟更新一次
            $curlData = $this->curlgood($code,$item['num_code']);
            Cache::set('curl'.$item['num_code'],$curlData,0);
            if (date('w') == 6 || date('w') == 0)
            {
                $curlData = Cache::get('curl'.$item['num_code']);
            }else{
                $curlData = $this->curlgood($code,$item['num_code']);
                Cache::set('curl'.$item['num_code'],$curlData,0);
            }
//            $curlData = Cache::get('curl'.$item['id']);
            // 时间 开盘 最高 最低 收盘为当前
//            dump($curlData);
//            die;
            $panData = explode(',',$curlData['Obj']['M1']);
            $panDataClose = ($curlData['Obj']['P']);
            $insetdata = [
                //获取产品id
                'media_id' => $item['id'],
                // 时间
                'y' => date('Y', $curlData['Obj']['Tick']),
                'm' => date('m', $curlData['Obj']['Tick']),
                'd' => date('d', $curlData['Obj']['Tick']),
                'h' => date('H', $curlData['Obj']['Tick']),
                'i' => date('i', $curlData['Obj']['Tick']),
                's' => date('s', $curlData['Obj']['Tick']),
                'w' => date('w', $curlData['Obj']['Tick']),
                'date' => date('Y-m-d H:i:s', $curlData['Obj']['Tick']),
                'time' => $curlData['Obj']['Tick'],
                'name' => $curlData['Obj']['S'],
                'open' => $panData[1],
                'close' => $panDataClose,
                'height' => $panData[2],
                'low' => $panData[3],
            ];
            $m = ShopData::where('time', $curlData['Obj']['Tick'])->where('name', $item['num_code'])->find();
            dump($insetdata,$m);
//            die;
            if ($m == NULL) {
                $m = ShopData::insert($insetdata);
                if ($m) {
                    dump($m . '新增成功');
                }
            }
            dump($insetdata);
        }
        // 请求保存完成/

        //发送消息
        $senddata['type']='index_goods_m';
        $senddata['data']=$senddata1;
        $req_data = json_encode($senddata);
        Gateway::sendToAll($req_data);

        return [$a,'ok'];
    }
    /** end */

    /**
     * @param $code
     * @param $good
     * @return mixed
     * 第一个 行情查询
     */
    private function curlgood($code,$good)
    {
        $host = "http://alirmcom2.market.alicloudapi.com";
        $path = "/query/com";
        $method = "GET";
        $appcode = $code;
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "symbol={$good}&withks=1&withticks=0";
        $bodys = "";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $curlData = json_decode(curl_exec($curl),true);
        dump($curlData);
        return $curlData;
    }
    /**
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 给用户发送数据 ok
     */
    public function sendMsg_s()
    {
        $title = '发送消息1s';
        $command = 'xdata:UserSendMsg';
        $code = sysqueue($title, $command, $later = 0, $data = [], $rscript = 0, $loops = 1);
        var_dump($code);
    }
    public function sendMsg_s1()
    {
        $goods = Cache::get('goods_s');
        if(empty($goods)) {
            $sql = 'SELECT sg.*, sd.* ,sd.id as last_id
                    FROM shop_goods sg
                    JOIN shop_data sd ON sg.id = sd.media_id
                    WHERE 	sg.status = 1 and sg.deleted = 0 and 
                           sd.id = (
                              SELECT MAX(id) FROM shop_data WHERE media_id = sg.id
                            );';
            $list = Db::query($sql);
            Cache::set('goods_s',$list,59);
        }
        $goods = Cache::get('goods_s');

        $xs_num = 100000;
        $dd = date('Y-m-d h:i:s');
        $tt = time();

        $dd1 = date('Y-m-d h:i');
        $dd2 = date('Ymdhi00');
        $dd3 = date('Ymdhi');

        $senddata = [];
        foreach ($goods as $good) {
            //生成随机数据
            $sell_bd = explode('-',$good['k_sell_bd']);
            $num1 = 10 ** strlen(substr(strrchr($sell_bd[0], "."), 1));
            $num2 = 10 ** strlen(substr(strrchr($sell_bd[1], "."), 1));

            $num3 = 10 ** strlen(substr(strrchr($good['height'], "."), 1));

            // 产品高度 - 整数
            $good_height = $num3*$good['height'];
            // 获取整数随机
            $intsj = mt_rand($num1*$sell_bd[0],$num2*$sell_bd[1]);
            dump(((rand(1, 10)>=5?$good_height+$intsj:$good_height-$intsj))/$num3);
//            dump(rand(1, 10)>=5?$good['height']*$num3,$intsj);die;

            die;
            $sj = mt_rand(0.00001*$xs_num,0.00005*$xs_num);
//            $sj = mt_rand($good['point_low']*$xs_num,$good['point_top']*$xs_num);
            $s_bd = number_format($sj/$xs_num,6);
            // 每秒随机 + -
            $is_bd = mt_rand(0,100);
            $s_val = $good['height'];
            $ts_v = $is_bd >= 50?$s_val+$s_bd:$s_val-$s_bd;
            dump($good['name']);die;
            $ShopDataInsert['name'] =$good['name'];
            $ShopDataInsert['media_id'] =$good['media_id'];
            $ShopDataInsert['val'] = (string)$ts_v;

            $ShopDataInsert['now_buy'] =(string)$ts_v;
            $ShopDataInsert['now_sell'] =(string)$ts_v;

            $ShopDataInsert['now_buy_arr'] =$this->str_k_v($ts_v);
            $ShopDataInsert['now_sell_arr'] =$this->str_k_v($ts_v);
            $ShopDataInsert['now_buy_status'] =rand(1,2);
            $ShopDataInsert['now_sell_status'] =rand(1,2);
            $ShopDataInsert['datetime'] =$dd;
            $ShopDataInsert['time'] =$tt;

            $ShopDataInsert['datetime1'] =$dd1;
            $ShopDataInsert['time1'] =strtotime($dd1);
            $ShopDataInsert['datetime2'] =$dd2;
            $ShopDataInsert['time2'] =strtotime($dd2);
            $ShopDataInsert['datetime3'] =$dd3;
            $ShopDataInsert['time3'] =strtotime($dd3);
            $ShopDataInsert['time_ce'] =time()*1000;


            $ShopDataInsert['open'] =strval($good['open']);
            $ShopDataInsert['height'] =strval($good['height']);
            $ShopDataInsert['low'] =strval($good['low']);
            $ShopDataInsert['close'] =strval($good['close']);

            $senddata[$ShopDataInsert['media_id']]=$ShopDataInsert;
        }
        //发送消息
        $d['type']='index_goods_s';
        $d['data']=$senddata;
        $req_data = json_encode($d);
        Gateway::sendToAll($req_data);
    }
    /**
     * 给用户发送数据 end
     */

    //队列任务-给每个用户发消息
    public function sendMemberMsg_old(){
        die;
        $user = Cache::store('file')->get('user_arr');
        foreach ($user as $a => $item) {
            $isOnline = Gateway::isUidOnline($item);
            if($isOnline == 0){
                unset($user[$a]);
            }
        }
        $user = Cache::store('file')->set('user_arr',$user);

        $goods = Cache::store('file')->get('goods');
        if(empty($goods)) {
            $goods = Db::table('shop_goods')->select();
            Cache::store('file')->set('goods',$goods);
        }
        $data = [];
        $id = [];
        $time1 = time();
        $xs_num = 1000000;
        //获取最近一条数据
        foreach ($goods as $good) {
            $id[] = $good['id'];
        }
        $id1 = [];
        $sql = 'SELECT media_id, MAX(id) as id FROM shop_data GROUP BY media_id';
        $list1 = Db::query($sql);
        foreach ($list1 as $good) {
            $id1[] = $good['id'];
        }
        // 缓存70s上次的值
        $last_list = Cache::get('last_list');
        if (empty($last_list)) {
            $last_list = Db::table('shop_data')->whereIn('id',$id1)->select()->toArray();
            $last_list1 = [];
            foreach ($last_list as $a=>$item) {
                $last_list1[$item['media_id']] = $item;
            }
            Cache::set('last_list',$last_list1,70);
        }
        $last_list = Cache::get('last_list');

        foreach ($goods as $good) {
            $sj = mt_rand($good['point_low']*$xs_num,$good['point_top']*$xs_num);
            // 每秒随机波动值
            $s_bd = number_format($sj/$xs_num,6);
            // 每秒随机 + -
            $is_bd = mt_rand(1,2);
            // 当前分钟产品的值
            $s_val = $last_list[$good['id']]['val'];
            $ts_v = $is_bd == 1?$s_val+$s_bd:$s_val-$s_bd;
            $data[] = [
                'id'=>$good['id'],
                'name'=>$good['name'],
                'now_sell_arr'=>$this->str_k_v($ts_v),
                'now_buy_arr'=>$this->str_k_v($ts_v),
                'now_sell_status'=>rand(1,2),
                'now_buy_status'=>rand(1,2),
                'val'=>$ts_v,
                'time'=>$time1,
            ];
        }
        $d['type']='index_goods';
        $d['data']=$data;
        $req_data = json_encode($d);
        Gateway::sendToAll($req_data);
        var_dump('发送消息');
    }
    protected function LS($n){
        if($n){
            return DB::table($n)->getlastsql();
        }
    }
    /**
     * 获取用户数据
     * @return array
     */
    protected function isuser()
    {
        // 检查接口类型
        $this->type = $this->request->header('api-name');
        $this->type = $this->type?$this->type:'xxx';
        // 检查token
        $token = $this->request->header('api-token');
        $token = $token?$token:'xxx';
        if($token == 'xxx' || $this->type == 'xxx'){
            return [9, '未登录', 'x'];
        }
        [$state, $info, $this->uuid] = UserTokenService::check($this->type, $token);

        if($info=='请重新登录，登录认证失效' || $info=='请重新登录，登录认证无效'){
            return [9, '登录无效', 'x'];
        }
        return [$state, $info, $this->uuid];
    }
    //绑定用户
    //初始化
    public function init()
    {
        $data = $this->_vali(['client_id.require' => '请填写cid']);
        $this->bind();

    }


    //按id返回产品交易信息
    public function findGoodsId()
    {
        $data = $this->_vali([
            'pid.require' => '请填写产品id'
        ]);
        $user = $this->isuser();
        if($user[0] == 9){
            $this->error('登录失败', [], 401);
        }
        // 查找产品
        $m = ShopGoods::find($data['pid']);
        if(empty($m)){
            $this->error('产品不存在');
        }
        //返回最新数据
        $f = Db::table('shop_data')->where('a.media_id',$data['pid'])
        ->alias('a')
        ->field('b.id,b.name,b.title,a.now_buy,a.now_sell,height')
        ->leftjoin('shop_goods b','b.id = a.media_id')
        ->order('id desc')->find();
        $f['now_buy_status']=0;
        $f['now_sell_status']=0;

        $f['now_buy_str'] = strval($f['height']);
        $f['now_sell_str'] = strval($f['height']);
        
        $f['now_buy'] = $this->str_k_v($f['height']);
        $f['now_sell'] = $this->str_k_v($f['height']);


        

        $this->success('操作成功',$f);
    }

    private function bind()
    {
        $user = $this->isuser();

        if($user[0] == 9){
            //如果5有登录，绑定id = x
            $uid = 'x';
        }else{
            //如果有登录，绑定id
            $uid = $user[2];
        }
        //client_v 鉴权 t time
        $data = $this->_vali([
            'client_id.require' => '请填写cid',
            'client_v.require' => 'error',
            't.require' => 'error'
        ]);
        if(time()-$data['t'] > 5){
            // $this->error('绑定失败', [], 0);
        }
        Gateway::bindUid($data['client_id'], $uid);
        
        //缓存用户id
        $v = [];
        Cache::store('file')->set('user_arr',$v);
        $user = Cache::store('file')->get('user_arr');
        array_push($user,$uid);
        $user = array_unique($user);
        Cache::store('file')->set('user_arr',$user);

        // $message = '{"type":"send_to_uid","uid":"xxxxx", "message":"...."}';
        // $req_data = json_decode($message, true);
        // Gateway::sendToClient($data['client_id'], $message);
    }



    /**
     * 获取分类数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCate()
    {
        $this->success('获取分类成功', ShopGoodsCate::treeData());
    }

    /**
     * 获取标签数据
     */
    public function getMark()
    {
        $this->success('获取标签成功', ShopGoodsMark::items());
    }
    //搜索产品--默认返回全部
    public function searchGoods()
    {
        $user = $this->isuser();
        //未登录
        if($user[0] == 9)
        {
            $this->error('登录失败', [], 401);
        }
        //产品类别
        $class1=Db::table('shop_goods_cate')->where(['status' => 1,'deleted' => 0])->cache(true,600)->select();
        $list = [];
        $a = 0;
        foreach($class1 as $v){
            $list[$a]['class_name'] = $v['name'];
            $list[$a][$v['name']] = ShopGoods::where(['deleted' => 0,'deleted' => 0,'cateids' => $v['id']])
            ->cache(true,60)
            ->field('id,name,remark,cateids')
            ->select();
            $a++;
        }
        $this->success('操作成功', $list);   
    }
    //添加收藏
    public function addGoods()
    {
        $data = $this->_vali(['pid.require' => '请选择产品']);
        $user = $this->isuser();
        //未登录
        if($user[0] == 9)
        {
            $this->error('登录失败', [], 401);
        }
        
        if($user[0] == 1){
            //已登录-添加收藏
            $DataUserMyCollect = new DataUserMyCollect;
            $m = $DataUserMyCollect->where([
                'uid'  =>  $user[2],
                'pid' =>  $data['pid']
            ])->find();
            if(!empty($m)){
                $this->success('已添加');
            }
            $DataUserMyCollect->cacheAlways()->save([
                'uid'  =>  $user[2],
                'pid' =>  $data['pid']
            ]);
            $this->success('操作成功');
        }else{
            $this->error('添加失败', [], 2);
        }
    }
    //删除收藏
    public function delGoods()
    {
        $data = $this->_vali(['pid.require' => '请选择产品']);
        $user = $this->isuser();
        //未登录
        if($user[0] == 9)
        {
            $this->error('登录失败', [], 401);
        }

        if($user[0] == 1){
            //已登录-删除收藏
//            $m = Db::table('data_user_my_collect')->where(['pid'=>$data['pid'],'uid'=>$user[2]])->delete();
            $m = Db::table('data_user_my_collect')->where(['id'=>$data['pid'],'uid'=>$user[2]])->delete();
            if($m){
                $this->success('操作成功');
            }else{
                $this->error('操作失败', [], 2);
            }
        }else{
            $this->error('请登录', [], 0);
        }
    }

    /**
     * @param $srt
     * @return array
     * 分割字符串
     */
    protected function str_k_v($srt){
        [$a,$b] = explode('.',$srt);

        if(strlen($a) >= 3){
            $v1 = $a.'.';
            
            if(strlen($b) >2){
                $v2 = substr($b,0,2);
                $v3 = substr($b,2,1);
            }else{
                $v2 = $b;
                $v3 = 0;
            }

        }else{
            $v1 = $a.'.'.substr($b,0,2);
            $v2 = substr($b,2,2);
            $v3 = substr($b,4,1);
        }
        if(!$v3){
            $v3 = 0;
        }
        return [$v1,$v2,$v3];
    }

    /**
     * 获取商品数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoods()
    {
        $user = $this->isuser();

        if($user[0] == 1){
            //已登录-查看自己的收藏

            /* $list = DB::table('data_user_my_collect')
                ->alias('my')
                ->field('my.id as id,g.id as myid,g.name,g.name,g.k_low,g.k_top,g.k_status,g.k_percent')
                ->leftJoin('shop_goods g','my.pid = g.id')
                ->where('my.uid',$this->uuid)->select()->toArray(); */
            $sql = 'SELECT my.id as id ,sg.id as myid,sd.id as data_id,sd.media_id,sd.height,sd.low,sg.name,sg.k_percent,sg.k_status,sg.k_low,sg.k_top
                    FROM shop_goods sg
                    JOIN shop_data sd ON sg.id = sd.media_id
                    JOIN data_user_my_collect AS my ON my.pid = sg.id
                    WHERE sg.status = 1 and sg.deleted = 0 and sd.id = (
                      SELECT MAX(id) FROM shop_data WHERE media_id = sg.id
                    ) and my.uid = '.$this->uuid.'
                    order by my.id desc
                    ;';
            $list = Db::query($sql);
            $data_info = '用户商品数据';
        }else{
            // $this->error('用户登录失败！', '{-null-}', 401);
//            $list = Db::table('shop_goods')->select()->toArray();
            $sql = 'SELECT sg.id as id,sd.id as data_id,sd.media_id,sd.height,sd.low,sg.name,sg.k_percent,sg.k_status,sg.k_low,sg.k_top
                FROM shop_goods sg
                JOIN shop_data sd ON sg.id = sd.media_id
                WHERE sg.status = 1 and sg.deleted = 0 and sd.id = (
                  SELECT MAX(id) FROM shop_data WHERE media_id = sg.id
                ) and sg.id <=  10 order by sg.id desc';
            $list = Db::query($sql);
            $data_info = '全部商品数据';
        }
        function str_k_v($srt){
            [$a,$b] = explode('.',$srt);
            if(strlen($a) >= 3){
                $v1 = $a.'.';

                if(strlen($b) >2){
                    $v2 = substr($b,0,2);
                    $v3 = substr($b,2,1);
                }else{
                    $v2 = $b;
                    $v3 = 0;
                }

            }else{
                $v1 = $a.'.'.substr($b,0,2);
                $v2 = substr($b,2,2);
                $v3 = substr($b,4,1);
            }
            if(!$v3){
                $v3 = 0;
            }
            return [$v1,$v2,$v3];
        }

        foreach($list as &$v){

            $v['now_sell_arr'] = str_k_v($v['height']);
            $v['now_buy_arr'] = str_k_v($v['height']);


            $v['now_buy'] = $v['height'];
            $v['now_sell'] = $v['height'];

            $v['time_v'] = date('H:i:s');
            $v['time_r_v'] = rand(5,100);
            $v['left_v'] = '-'.rand(1,100);

            $v['now_sell_status'] = 0;
            $v['now_buy_status'] = 0;
        }
        $this->success($data_info, ['data'=>$list]);
    }
}