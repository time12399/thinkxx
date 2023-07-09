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

namespace app\data\command;

use app\axapi\model\ShopData;
use think\admin\Command;
use think\admin\Exception;
use think\console\Input;
use think\console\Output;

use think\facade\Cache;
use think\facade\Db;

/**
 * 用户等级重算处理
 * Class UserLevel
 * @package app\data\command
 */
class SendMinuteMsg extends Command
{
    protected function configure()
    {
        $this->setName('xdata:SendMinuteMsg');
        $this->setDescription('发送消息60s');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     * @throws Exception
     */
    protected function execute(Input $input, Output $output)
    {
        $goods = Cache::get('goods_m');
        if(empty($goods)) {
            $sql = 'SELECT sg.*, sd.* ,sd.id as last_id
                    FROM shop_goods sg
                    JOIN shop_data sd ON sg.id = sd.media_id
                    WHERE sd.id = (
                      SELECT MAX(id) FROM shop_data WHERE media_id = sg.id
                    );
                    ';
            $list = Db::query($sql);
            Cache::set('goods_m',$list,6000);
        }
        $goods = Cache::get('goods_m');

        $xs_num = 1000000;
        $y = date('Y');
        $m = date('m');
        $d = date('d');
        $h = date('H');
        $i = date('i');
        $s = date('s');
        $dd = date('y-m-d h:i:s');
        $tt = time();
        $ShopDataInsert = [
            'y'=>$y,
            'm'=>$m,
            'd'=>$d,
            'h'=>$h,
            'i'=>$i,
            's'=>$s,
            'date'=>$dd,
            'time'=>$tt
        ];
        $a=0;
        foreach ($goods as $good) {
            $a++;
            //生成随机数据
            $sj = mt_rand($good['point_low']*$xs_num,$good['point_top']*$xs_num);
            $s_bd = number_format($sj/$xs_num,6);
            // 每秒随机 + -
            $is_bd = mt_rand(0,100);
            $s_val = $good['val'];
            $ts_v = $is_bd >= 50?$s_val+$s_bd:$s_val-$s_bd;
            $ShopDataInsert['name'] =$good['name'];
            $ShopDataInsert['media_id'] =$good['media_id'];
            $ShopDataInsert['val'] =$ts_v;
            ShopData::insert($ShopDataInsert);
        }

        $this->setQueueSuccess(date('Y-m-d H:i:s')."生成{$a}条数据下次".date('Y-m-d H:i:s',strtotime("+1 minute")).'执行');
    }
}