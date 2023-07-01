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
use app\axapi\model\ShopGoodsCate;
use app\axapi\model\ShopGoodsMark;
use app\axapi\service\ExpressService;
use app\axapi\service\GoodsService;
use think\admin\Controller;


use app\axapi\service\UserTokenService;

use app\axapi\model\DataUserMyCollect;

use think\facade\Db;
use app\validate\JsonValidate;

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
        return [$state, $info, $this->uuid];
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
        $user = ($this->isuser());
        //未登录
        if($user[0] == 0)
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
        $user = ($this->isuser());
        //未登录
        if($user[0] == 0)
        {
            $this->error('登录失败', [], 401);
        }
        
        if($user[0] == 1){
            //已登录-添加收藏
            $DataUserMyCollect = new DataUserMyCollect;
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
        $user = ($this->isuser());
        //未登录
        if($user[0] == 0)
        {
            $this->error('登录失败', [], 401);
        }

        if($user[0] == 1){
            //已登录-删除收藏
            $m = Db::table('data_user_my_collect')->where(['pid'=>$data['pid'],'uid'=>$user[2]])->delete();
            if($m){
                $this->success('操作成功');
            }else{
                $this->error('操作失败', [], 2);
            }
        }else{
            $this->error('操作失败', [], 2);
        }
    }

    /**
     * 获取商品数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoods()
    {
        $user = ($this->isuser());
        //未登录
        if($user[0] == 9)
        {
            // 商品数据处理
            // $query = ShopGoods::mQuery()->like('name,marks,cateids,payment')->equal('code,vip_entry');
            // $result = $query->where(['deleted' => 0, 'status' => 1])->order('sort desc,id desc')->field('id,sort,name,k_low,k_top,k_status,k_percent')->page(true, false, false, 2);
            // if (count($result['list']) > 0) GoodsService::bindData($result['list']);
            
            $list = Db::table('shop_goods')
                ->alias('b')
                ->leftjoin('shop_data a','a.media_id = b.id')
                ->field('b.id,b.sort,b.name,b.k_low,b.k_top,b.k_status,b.k_percent,a.date,a.date,a.now_buy,a.now_sell')
                ->cache(true,60)
                ->paginate($this->page);

            $this->success('获取商品数据', $list);
        }

        if($user[0] == 1){
            //已登录-查看自己的收藏
            // $list = DataUserMyCollect::with('ShopGoods')->select();
            // SELECT a.*,b.* FROM data_user_my_collect as a LEFT JOIN shop_goods as b ON a.ppid = b.id
            // var_dump($list);

            $list = Db::table('data_user_my_collect')
                ->alias('a')
                ->field('b.id,b.sort,b.name,b.k_low,b.k_top,b.k_status,b.k_percent,c.date,c.date,c.now_buy,c.now_sell')
                ->leftjoin('shop_goods b','a.pid = b.id')
                ->where('a.uid',1)
                ->leftjoin('shop_data c','c.media_id = b.id')
                ->where(['a.is_deleted' => 0])
                ->cache(true,60)
                ->paginate($this->page);

            $this->success('获取商品数据', $list);
        }else{
            $this->error('用户登录失败！', '{-null-}', 401);
        }
    }

    /**
     *  获取配送区域
     */
    public function getRegion()
    {
        $this->success('获取区域成功', ExpressService::region(3, 1));
    }
}