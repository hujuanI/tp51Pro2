<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    protected  $chatModel;
    public function __construct()
    {
        $this->chatModel = new \app\common\model\Xchats();
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // 向当前 client_id 发送数据
        Gateway::sendToClient($client_id,json_encode(['type' => 'init', 'client_id' => $client_id]));
    }
    
   /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
   public static function onMessage($client_id, $message)
   {
       //示例： $message = '{"type":"send_to_uid","uid":"xxxxx", "message":"...."}'
       $message_data = json_decode($message,true);
       if ($message_data){
           //TODO 方便区分信息传递类型
           $type = $message_data['type'];
           // 发送人ID,此处为数据库中管理员的ID
           $from_id = $message_data['from_id'];
           // 接收人ID
           $to_id = isset($message_data['to_id'])?$message_data['to_id']:0;
           switch ($type){
               case 'bind':
                   //将client_id与uid绑定，用来唯一确定一个客户端用户或者设备
                   Gateway::bindUid($client_id,$from_id);
                   return;
               case 'online':
                   //判断接收人是否在线
                   $onlineStatus = Gateway::isUidOnline($to_id);
                   Gateway::sendToUid($from_id, json_encode(['type'=>'online','to_id'=>$to_id,'status'=>$onlineStatus]));
                   return;
               case 'say':
                   //发送文字
                   $text = nl2br(htmlspecialchars($message_data['content']));
                   $sayData = [
                       'type' => 'say',
                       'content' => $text,
                       'from_id' => $from_id,
                       'to_id' => $to_id,
                       'log_time' => time()
                   ];
                   if (Gateway::isUidOnline($to_id)){
                       $sayData['is_read'] = 1;
                       Gateway::sendToUid($to_id, json_encode($sayData));
                   }else{
                       $sayData['is_read'] = 0;
                   }
                   Gateway::sendToUid($from_id,json_encode($sayData));
                   return;
               case "say_img":
                   //发送图片
                   $img_name = $message_data['data'];
                   $sayData=[
                       'type'=>'say_img',
                       'from_id'=>$from_id,
                       'to_id'=>$to_id,
                       'content'=>$img_name,
                       'log_time' => time()
                   ];
                   if (Gateway::isUidOnline($to_id)){
                       Gateway::sendToUid($to_id,json_encode($sayData));
                   }
                   Gateway::sendToUid($from_id,json_encode($sayData));
                   return;
               case "timers":
                   return;
               default:
                   break;
           }
       }else{
           return;
       }
   }
   
   /**
    * 当用户断开连接时触发
    * 当client_id下线（连接断开）时会自动与uid解绑，开发者无需在onClose事件调用
    * @param int $client_id 连接id
    */
   public static function onClose($client_id)
   {
       // 向所有人发送
       $send_msg = json_encode(['type'=>'online','to_id'=>$client_id,'status'=>0,'msg'=>'掉线了！']);
       GateWay::sendToClient($client_id,$send_msg);
   }
}
