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

use app\axapi\model\BaseUserMessage;
use app\axapi\model\ShopData;
use think\admin\Controller;
use think\admin\helper\QueryHelper;
use think\admin\model\SystemBase;
use think\facade\Cache;

/**
 * 基础数据接口
 * Class Data
 * @package app\axapi\controller\api
 */
class Data extends Controller
{

    /**
     * 获取指定数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getData()
    {
        $data = $this->_vali(['name.require' => '数据名称不能为空！']);
        $extra = ['about', 'slider', 'agreement', 'cropper']; // 其他数据
        if (in_array($data['name'], $extra) || isset(SystemBase::items('页面内容')[$data['name']])) {
            $this->success('获取数据对象', sysdata($data['name']));
        } else {
            $this->error('获取数据失败', []);
        }
    }

    public function curl_file_get_contents($durl){
        // header传送格式
        $headers = array(
            // "Referer:https://finance.sina.com.cn",
        );
        // 初始化
        $curl = curl_init();
        // 设置url路径
        curl_setopt($curl, CURLOPT_URL, $durl);
        // 将 curl_exec()获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true) ;
        // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true) ;
        // 添加头信息
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        // CURLINFO_HEADER_OUT选项可以拿到请求头信息
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        // 不验证SSL
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        // 执行
        $data = curl_exec($curl);
        // 打印请求头信息
        // echo curl_getinfo($curl, CURLINFO_HEADER_OUT);
        // 关闭连接
        curl_close($curl);
        // 返回数据
        return $data;
    }
    public function getDate(){
        //查询5分钟k线 wherein 05 10 15 20 25 30 35 40 45 50 55 00
        //查询15分钟k线 wherein 15 30 45 00
        //上次请求时间
        $beforeTime = Cache::get('beforeTime')-300;
        if(time() - $beforeTime < 30){
            echo '请求频繁429';
            die;
        }
        $url = 'http://apilayer.net/api/live?access_key=99695110f5488396b2fecfdbb5f8286f&currencies=ZAR,GBP,CAD,PLN&source=USD&format=1';
        $getdata = $this->curl_file_get_contents($url);
        $getdata = json_decode($getdata);

        if($getdata->success){
            echo "<pre>";
            // var_dump($getdata);
            var_dump($getdata->quotes);
            var_dump(date('Y-m-d H:i:s',$getdata->timestamp));
            // ShopData::insert();
            Cache::set('beforeTime',time(), 3600);
        }else{
            echo 2;
        }
    }



    /**
     * 图片内容数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getSlider()
    {
        $this->keys = input('keys', '首页图片');
        if (isset(SystemBase::items('图片内容')[$this->keys])) {
            $this->success('获取图片内容', sysdata($this->keys));
        } else {
            $this->error('获取图片失败', []);
        }
    }

    /**
     * 系统通知数据
     */
    public function getNotify()
    {
        BaseUserMessage::mQuery(null, function (QueryHelper $query) {
            if (($id = input('id')) > 0) {
                BaseUserMessage::mk()->where(['id' => $id])->inc('num_read')->update([]);
            }
            $query->equal('id')->where(['status' => 1, 'deleted' => 0]);
            $this->success('获取系统通知', $query->order('sort desc,id desc')->page(true, false, false, 20));
        });
    }
}