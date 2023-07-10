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

use think\admin\Command;
use think\admin\Exception;
use think\console\Input;
use think\console\Output;


use app\axapi\controller\api\Goods;

/**
 * 发送消息60s
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
        $g = new Goods($this->app);
        $a = $g->sendMsg_m1();
        $this->setQueueSuccess(date('y-m-d H:i:s')."生成{$a[0]}条,成功{$a[1]}次，下次".date('H:i:s',strtotime("+1 minute")).'执行');
    }
}