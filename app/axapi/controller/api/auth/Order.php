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

namespace app\axapi\controller\api\auth;

use app\axapi\controller\api\Auth;
use app\axapi\model\BaseUserPayment;
use app\axapi\model\DataUser;
use app\axapi\model\DataUserAddress;
use app\axapi\model\ShopGoods;
use app\axapi\model\ShopGoodsItem;
use app\axapi\model\ShopOrder;
use app\axapi\model\ShopOrderItem;
use app\axapi\model\ShopOrderSend;
use app\axapi\service\ExpressService;
use app\axapi\service\GoodsService;
use app\axapi\service\OrderService;
use app\axapi\service\PaymentService;
use app\axapi\service\UserAdminService;
use Exception;
use stdClass;
use think\admin\extend\CodeExtend;
use think\exception\HttpResponseException;



use app\axapi\service\UserRebateService;


/**
 * 用户订单数据接口
 * Class Order
 * @package app\axapi\controller\api\auth
 */
class Order extends Auth
{
    /**
     * 控制器初始化
     */
    protected function initialize()
    {
        parent::initialize();
        if (empty($this->user['status'])) {
            $this->error('账户已被冻结，不能操作订单数据哦！');
        }
        $this->page=10;
    }

    /**
     * 获取订单列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get()
    {
        $map = ['uuid' => $this->uuid, 'deleted_status' => 0];
        $query = ShopOrder::mQuery()->in('status')->equal('order_no');
        $result = $query->where($map)->order('id desc')->page(true, false, false, 20);
        if (count($result['list']) > 0) OrderService::buildData($result['list']);
        $this->success('获取订单数据成功！', $result);
    }

    /**
     * @return void
     * 获取当前交易中的订单
     */
    public function getMyNowOrder()
    {
        $result = ShopOrder::where('uuid',$this->uuid)
            ->alias('a')
            ->field('a.id,b.name,a.k_buy_status,a.k_iswin,a.k_money,a.create_price,a.finish_price,a.user_time_end,a.k_num')
            ->leftJoin('shop_goods b','a.ppid=b.id')
            ->order('a.id asc')
            ->paginate($this->page);
        $this->success('获取订单数据成功！', $result);
    }
    /**
     * @return void
     * @throws \think\db\exception\DbException
     * 历史订单-历史-价位
     */
    public function getMyOrder()
    {
        $result = ShopOrder::where('uuid',$this->uuid)
            ->alias('a')
            ->field('a.id,b.name,a.k_buy_status,a.k_iswin,a.k_money,a.create_price,a.finish_price,a.user_time_end,a.k_num')
            ->leftJoin('shop_goods b','a.ppid=b.id')
            ->order('a.id asc')
            ->paginate($this->page);
        $this->success('获取订单数据成功！', $result);
    }
    /**
     * 提交订单
     */
    public function addTrade()
    {
        $data = $this->_vali([
            'pid.require' => '产品不存在',
            'time.require' => '请填写请求时间',
            'status.require' => '请填写状态',
            'price.require' => '请填写价格',
            'myamount_.require'=>'请选择手数',
            'type.require'=>'请选择手数'
        ]);
        // 检查用户状态
        $this->checkUserStatus();

        // 余额不足
        $myamount_  = $data['myamount_']*1000;
        $amount = DataUser::value('my_total');
        if($amount<$myamount_) $this->error('余额不足');
        
        $goodsInfo = ShopGoods::mk()->where(['id' =>$data['pid'], 'status' => 1, 'deleted' => 0])->find();

        if (empty($goodsInfo)) $this->error('商品查询异常');

        if($goodsInfo['isopen'] == 0){
            $this->error('已休市');
        }
        $today = date("N");
        // echo $today;
        [$open,$close]= explode('~',$goodsInfo['opentime_'.$today]);
        $now_time = date('H:i:s');
        if($open<$now_time && $now_time<$close){
            //开始处理下单
            $order['uuid'] = $this->uuid;
            $order['order_no'] = CodeExtend::uniqidDate(18, 'N');

            
            $order = [
                'uuid'            => $order['uuid'],
                'ppid'            => $goodsInfo['id'],
                'ppcode'          => $goodsInfo['code'],
                'order_no'        => $order['order_no'],
                'amount_real'     => $myamount_,
                'amount_total'    => $myamount_,
                'amount_goods'     => $myamount_,
                'payment_amount'  => $myamount_,
                'amount_discount'  => $myamount_,
                'payment_status'  => 1,
                'create_price'  => $data['price'],
                'k_buy_status'  => $data['status'],
                'status'  => 4,
                'k_money'=>0,
                'k_iswin'=>0,
                'k_status'=>1
            ];

            try {
                // 写入商品数据
                $this->app->db->transaction(function () use ($order,$myamount_) {
                    ShopOrder::mk()->insert($order);
                    // 减少余额
                    DataUser::where('id',$this->uuid)->dec('my_total',$myamount_)->update();
                });
                // 触发订单创建事件
                $this->app->event->trigger('ShopOrderCreate', $order['order_no']);
                // 组装订单商品数据
                // $order['items'] = $items;
                // 返回处理成功数据
                $this->success('商品下单成功',[]);
            } catch (HttpResponseException $exception) {
                throw $exception;
            } catch (Exception $exception) {
                $this->error("商品下单失败，{$exception->getMessage()}");
            }

        }else{
            $this->error('已休市');
        }
    }
    /**
     * 用户创建订单
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function add()
    {die;
        // 检查用户状态
        $this->checkUserStatus();
        // 商品规则
        $rules = $this->request->post('item_id', '');
        $myamount_ = $this->request->post('myamount_', '');
       
        if (empty($rules)) $this->error('商品不能为空');
        if (empty($myamount_)) $this->error('请选择手数');
        // 订单数据
        $order['uuid'] = $this->uuid;
        $order['order_no'] = CodeExtend::uniqidDate(18, 'N');
        // 代理处理
        $order['puid1'] = input('from', $this->user['pid1']);
        if ($order['puid1'] == $this->uuid) $order['puid1'] = 0;
        if ($order['puid1'] > 0) {
            $map = ['id' => $order['puid1'], 'status' => 1];
            $order['puid2'] = DataUser::mk()->where($map)->value('pid2');
            if (is_null($order['puid2'])) $this->error('代理异常');
        }
        // 订单商品处理
        $goodsInfo = ShopGoods::mk()->where(['id' => $rules, 'status' => 1, 'deleted' => 0])->find();
        if (empty($goodsInfo)) $this->error('商品查询异常');
        
        $myamount_  = $myamount_*100; 
        $order = [
            'uuid'            => $order['uuid'],
            'ppid'            => $goodsInfo['id'],
            'ppcode'          => $goodsInfo['code'],
            'order_no'        => $order['order_no'],
            'amount_real'     => $myamount_,
            'amount_total'    => $myamount_,
            'payment_amount'  => $myamount_,
            'payment_status'  => 1,
            'create_price'  => 1,
            'status'  => 4,
        ];
        try {
            // 写入商品数据
            $this->app->db->transaction(function () use ($order) {
                ShopOrder::mk()->insert($order);
            });
            // 触发订单创建事件
            $this->app->event->trigger('ShopOrderCreate', $order['order_no']);
            // 组装订单商品数据
            // $order['items'] = $items;
            // 返回处理成功数据
            $this->success('商品下单成功',[]);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            $this->error("商品下单失败，{$exception->getMessage()}");
        }
    }

    /**
     * 获取用户折扣
     */
    public function discount()
    {
        $data = $this->_vali(['discount.require' => '折扣编号不能为空！']);
        [, $rate] = OrderService::discount(intval($data['discount']), $this->user['vip_code']);
        $this->success('获取用户折扣', ['rate' => $rate]);
    }

    /**
     * 模拟计算订单运费
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function express()
    {
        $data = $this->_vali([
            'uuid.value'       => $this->uuid,
            'code.require'     => '地址不能为空',
            'order_no.require' => '单号不能为空',
        ]);

        // 用户收货地址
        $map = ['uuid' => $this->uuid, 'code' => $data['code']];
        $addr = DataUserAddress::mk()->where($map)->find();
        if (empty($addr)) $this->error('收货地址异常');

        // 订单状态检查
        $map = ['uuid' => $this->uuid, 'order_no' => $data['order_no']];
        $tCount = ShopOrderItem::mk()->where($map)->sum('truck_number');

        // 根据地址计算运费
        $map = ['status' => 1, 'deleted' => 0, 'order_no' => $data['order_no']];
        $tCode = ShopOrderItem::mk()->where($map)->column('truck_code');
        [$amount, , , $remark] = ExpressService::amount($tCode, $addr['province'], $addr['city'], $tCount);
        $this->success('计算运费成功', ['amount' => $amount, 'remark' => $remark]);
    }

    /**
     * 订单信息完成
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function perfect()
    {
        $data = $this->_vali([
            'uuid.value'       => $this->uuid,
            'code.require'     => '地址不能为空',
            'order_no.require' => '单号不能为空',
        ]);

        // 用户收货地址
        $map = ['uuid' => $this->uuid, 'code' => $data['code'], 'deleted' => 0];
        $addr = DataUserAddress::mk()->where($map)->find();
        if (empty($addr)) $this->error('收货地址异常');

        // 订单状态检查
        $map1 = ['uuid' => $this->uuid, 'order_no' => $data['order_no']];
        $order = ShopOrder::mk()->where($map1)->whereIn('status', [1, 2])->find();
        if (empty($order)) $this->error('不能修改地址');
        if (empty($order['truck_type'])) $this->success('无需快递配送', ['order_no' => $order['order_no']]);

        // 根据地址计算运费
        $map2 = ['status' => 1, 'deleted' => 0, 'order_no' => $data['order_no']];
        $tCount = ShopOrderItem::mk()->where($map1)->sum('truck_number');
        $tCodes = ShopOrderItem::mk()->where($map2)->column('truck_code');
        [$amount, $tCount, $tCode, $remark] = ExpressService::amount($tCodes, $addr['province'], $addr['city'], $tCount);

        // 创建订单发货信息
        $express = [
            'template_code'   => $tCode, 'template_count' => $tCount, 'uuid' => $this->uuid,
            'template_remark' => $remark, 'template_amount' => $amount, 'status' => 1,
        ];
        $express['order_no'] = $data['order_no'];
        $express['address_code'] = $data['code'];
        $express['address_datetime'] = date('Y-m-d H:i:s');

        // 收货人信息
        $express['address_name'] = $addr['name'];
        $express['address_phone'] = $addr['phone'];
        $express['address_idcode'] = $addr['idcode'];
        $express['address_idimg1'] = $addr['idimg1'];
        $express['address_idimg2'] = $addr['idimg2'];

        // 收货地址信息
        $express['address_province'] = $addr['province'];
        $express['address_city'] = $addr['city'];
        $express['address_area'] = $addr['area'];
        $express['address_content'] = $addr['address'];

        ShopOrderSend::mUpdate($express, 'order_no');
        data_save(ShopOrderSend::class, $express, 'order_no');
        // 组装更新订单数据
        $update = ['status' => 2, 'amount_express' => $express['template_amount']];
        // 重新计算订单金额
        $update['amount_real'] = $order['amount_discount'] + $amount - $order['amount_reduct'];
        $update['amount_total'] = $order['amount_goods'] + $amount;
        // 支付金额不能为零
        if ($update['amount_real'] <= 0) $update['amount_real'] = 0.00;
        if ($update['amount_total'] <= 0) $update['amount_total'] = 0.00;
        // 更新用户订单数据
        $map = ['uuid' => $this->uuid, 'order_no' => $data['order_no']];
        if (ShopOrder::mk()->where($map)->update($update) !== false) {
            // 触发订单确认事件
            $this->app->event->trigger('ShopOrderPerfect', $order['order_no']);
            // 返回处理成功数据
            $this->success('订单确认成功', ['order_no' => $order['order_no']]);
        } else {
            $this->error('订单确认失败');
        }
    }

    /**
     * 获取支付支付数据
     */
    public function channel()
    {
        $data = $this->_vali(['uuid.value' => $this->uuid, 'order_no.require' => '单号不能为空']);
        $payments = ShopOrder::mk()->where($data)->value('payment_allow');
        if (empty($payments)) $this->error('获取订单支付参数失败');
        // 读取支付通道配置
        $query = BaseUserPayment::mk()->where(['status' => 1, 'deleted' => 0]);
        $query->whereIn('code', str2arr($payments))->whereIn('type', PaymentService::getTypeApi($this->type));
        $result = $query->order('sort desc,id desc')->column('type,code,name,cover,content,remark', 'code');
        foreach ($result as &$vo) $vo['content'] = ['voucher_qrcode' => json_decode($vo['content'])->voucher_qrcode ?? ''];
        $this->success('获取支付参数数据', array_values($result));
    }

    /**
     * 获取订单支付状态
     * @throws \think\db\exception\DbException
     */
    public function payment()
    {
        $data = $this->_vali([
            'uuid.value'            => $this->uuid,
            'order_no.require'      => '单号不能为空',
            'order_remark.default'  => '',
            'payment_code.require'  => '支付不能为空',
            'payment_back.default'  => '', # 支付回跳地址
            'payment_image.default' => '', # 支付凭证图片
        ]);
        [$map, $order] = $this->getOrderData();
        if ($order['status'] !== 2) $this->error('不能发起支付');
        if ($order['payment_status'] > 0) $this->error('已经完成支付');
        // 更新订单备注
        if (!empty($data['order_remark'])) {
            ShopOrder::mk()->where($map)->update([
                'order_remark' => $data['order_remark'],
            ]);
        }
        // 自动处理用户字段
        $openid = '';
        if (in_array($this->type, [UserAdminService::API_TYPE_WXAPP, UserAdminService::API_TYPE_WECHAT])) {
            $openid = $this->user[UserAdminService::TYPES[$this->type]['auth']] ?? '';
            if (empty($openid)) $this->error("发起支付失败");
        }
        try {
            // 返回订单数据及支付发起参数
            $type = $order['amount_real'] <= 0 ? 'empty' : $data['payment_code'];
            $param = PaymentService::instance($type)->create($openid, $order['order_no'], $order['amount_real'], '商城订单支付', '', $data['payment_back'], $data['payment_image']);
            $this->success('获取支付参数', ['order' => ShopOrder::mk()->where($map)->find() ?: new stdClass(), 'param' => $param]);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * 主动取消未支付的订单
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function cancel()
    {
        [$map, $order] = $this->getOrderData();
        if (in_array($order['status'], [1, 2, 3])) {
            $result = ShopOrder::mk()->where($map)->update([
                'status'          => 0,
                'cancel_status'   => 1,
                'cancel_remark'   => '用户主动取消订单',
                'cancel_datetime' => date('Y-m-d H:i:s'),
            ]);
            if ($result !== false && OrderService::stock($order['order_no'])) {
                // 触发订单取消事件
                $this->app->event->trigger('ShopOrderCancel', $order['order_no']);
                // 返回处理成功数据
                $this->success('订单取消成功');
            } else {
                $this->error('订单取消失败');
            }
        } else {
            $this->error('订单不可取消');
        }
    }

    /**
     * 用户主动删除已取消的订单
     * @throws \think\db\exception\DbException
     */
    public function remove()
    {
        [$map, $order] = $this->getOrderData();
        if (empty($order)) $this->error('读取订单失败');
        if ($order['status'] == 0) {
            $result = ShopOrder::mk()->where($map)->update([
                'status'           => 0,
                'deleted_status'   => 1,
                'deleted_remark'   => '用户主动删除订单',
                'deleted_datetime' => date('Y-m-d H:i:s'),
            ]);
            if ($result !== false) {
                // 触发订单删除事件
                $this->app->event->trigger('ShopOrderRemove', $order['order_no']);
                // 返回处理成功数据
                $this->success('订单删除成功');
            } else {
                $this->error('订单删除失败');
            }
        } else {
            $this->error('订单不可删除');
        }
    }

    /**
     * 订单确认收货
     * @throws \think\db\exception\DbException
     */
    public function confirm()
    {
        [$map, $order] = $this->getOrderData();
        if ($order['status'] == 5) {
            if (ShopOrder::mk()->where($map)->update(['status' => 6]) !== false) {
                // 触发订单确认事件
                $this->app->event->trigger('ShopOrderConfirm', $order['order_no']);
                // 返回处理成功数据
                $this->success('订单确认成功');
            } else {
                $this->error('订单确认失败');
            }
        } else {
            $this->error('订单确认失败');
        }
    }

    /**
     * 获取输入订单
     * @return array [map, order]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function getOrderData(): array
    {
        $map = $this->_vali([
            'uuid.value'       => $this->uuid,
            'order_no.require' => '单号不能为空',
        ]);
        $order = ShopOrder::mk()->where($map)->find();
        if (empty($order)) $this->error('读取订单失败');
        return [$map, $order];
    }

    /**
     * 订单状态统计
     */
    public function total()
    {
        $data = ['t0' => 0, 't1' => 0, 't2' => 0, 't3' => 0, 't4' => 0, 't5' => 0, 't6' => 0];
        $query = ShopOrder::mk()->where(['uuid' => $this->uuid, 'deleted_status' => 0]);
        foreach ($query->field('status,count(1) count')->group('status')->cursor() as $item) {
            $data["t{$item['status']}"] = $item['count'];
        }
        $this->success('获取订单统计', $data);
    }

    /**
     * 物流追踪查询
     */
    public function track()
    {
        try {
            $data = $this->_vali([
                'code.require'   => '快递不能为空',
                'number.require' => '单号不能为空'
            ]);
            $result = ExpressService::query($data['code'], $data['number']);
            empty($result['code']) ? $this->error($result['info']) : $this->success('快递追踪信息', $result);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }
}