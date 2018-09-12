<?php

namespace teddymail\yii2rabbitmq;

use Yii;
use yii\base\Component;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Queue extends Component
{
    /**
     * send Const
     */
    const EVENT_QUEUE_BEFORE_SEND = 'event_queue_before_send';
    const EVENT_QUEUE_BEFORE_SEND_BATCH = 'event_queue_before_send_batch';
    const EVENT_QUEUE_AFTER_SEND = 'event_queue_after_send';
    const EVENT_QUEUE_AFTER_SEND_BATCH = 'event_queue_after_send_batch';

    /**
     * read Const
     */
    const EVENT_QUEUE_BEFORE_READ = 'event_queue_before_read';

    public $client;
    public $name;

    private $queue;

    public function init()
    {
        parent::init();
        $this->client->queue_declare($this->name, false, true, false, false);
        $this->queue = $this->client;
    }

    public function __call($method_name, $args)
    {
        if (method_exists($this->queue, $method_name)) {
            return call_user_func_array([$this->queue, $method_name], $args);
        } else {
            return parent::__call($method_name, $args);
        }
    }

    /**
     * 同步发送
     * @param $messageBody 消息内容
     * @return bool 成功或失败
     */
    public function send($messageBody, $delaySeconds = null, $priority = null)
    {
        $event = MnsEvent::create(MnsEvent::QUEUE, $this->name, MnsEvent::STATUS_PRE);
        try {
            $event->request = new AMQPMessage($messageBody);
            $this->trigger(self::EVENT_QUEUE_BEFORE_SEND, $event);
            if (!is_null($delaySeconds)) {
                $this->sendDelayMessage($event->request, $delaySeconds);
                //延时消息统一放到waitSendExchange下进行暂存
                $this->queue->basic_publish($event->request, 'waitSendExchange', $this->name);
            }else{
                $this->queue->basic_publish($event->request,'',$this->name);
            }
            $event->status = MnsEvent::STATUS_SEND;
            $this->trigger(self::EVENT_QUEUE_AFTER_SEND, $event);
            return true;
        } catch (\ErrorException $e) {
            $event->status = MnsEvent::STATUS_FAIL;
            $this->trigger(self::EVENT_QUEUE_AFTER_SEND, $event);
            Yii::error("消息发送错误({$e->getCode()}): {$e->getFile()}\n{$e->getMessage()}\n{$e->getTraceAsString()}", 'rabbitmq.send');
        }
        return false;
    }

    /**
     * 发送延时消息
     * @param $message
     * @param $delaySeconds
     */
    public function sendDelayMessage($message, $delaySeconds)
    {
        //设置消息延时时间消息是毫秒为单位需要转化
        $millisecond = $delaySeconds * 1000;
        $message->set("expiration", $millisecond);
        //定义等待exchange
        $this->queue->exchange_declare('waitSendExchange', 'fanout', false, false, false);
        ////定义过期exchange 尝试通过routeKey来区分发送到那个队列里
        $this->queue->exchange_declare('expireExchange', 'direct', false, false, false);
        //定义过期queue
        $this->queue->queue_declare("expireQueue",false,false,false,false);
        //定义等待queue
        $this->queue->queue_declare("waitSendQueue",false,false,false,false,false,new AMQPTable(array("x-dead-letter-exchange"=>"expireExchange")));
        $this->queue->queue_bind("waitSendQueue","waitSendExchange");
        $this->queue->queue_bind("expireQueue","expireExchange");
        //决定要去哪里
        $this->queue->queue_bind($this->name,"expireExchange",$this->name);
    }

    /**
     * 批量发送
     * @param $messageBody 消息内容
     * @return bool 成功或失败
     */
    public function sendBatch($messageBodys, $delaySeconds = null, $priority = null)
    {
        try {
            $messageBodys = (array) $messageBodys;
            $messageBodyss = array_chunk($messageBodys, 16);
            foreach ($messageBodyss as $messageBodys) {
                $event = MnsEvent::create(MnsEvent::QUEUE, $this->name, MnsEvent::STATUS_PRE);
                $items = [];
                foreach ($messageBodys as $messageBody) {
                    //批量组装AMQPMessage
                    $message = new AMQPMessage($messageBody);
                    //传递批量消息
                    if (!is_null($delaySeconds)) {
                        $this->sendDelayMessage($message,$delaySeconds);
                        $this->queue->batch_basic_publish($message, 'waitSendExchange', $this->name);
                    } else {
                        $this->queue->batch_basic_publish($message, '', $this->name);
                    }
                    $items[] = $message;
                }
                $event->request = $items;
                $this->trigger(self::EVENT_QUEUE_BEFORE_SEND_BATCH, $event);
                $this->queue->publish_batch();
                $event->status = MnsEvent::STATUS_SEND;
                $this->trigger(self::EVENT_QUEUE_AFTER_SEND_BATCH, $event);
            }
            return true;
        } catch (\ErrorException $e) {
            $event = (isset($event) && $event) ? $event : MnsEvent::create(MnsEvent::QUEUE, $this->name, MnsEvent::STATUS_PRE);
            $event->status = MnsEvent::STATUS_FAIL;
            $this->trigger(self::EVENT_QUEUE_AFTER_SEND_BATCH, $event);
            Yii::error("消息发送错误({$e->getCode()}): {$e->getFile()}\n{$e->getMessage()}\n{$e->getTraceAsString()}", 'rabbitmq.send_batch');
        }
        return false;
    }

    private $msgHandles = [];

    /**
     * 接收消息
     * @param $waitSeconds 等待的秒数
     * @return string 消息内容
     */
    public function reserve($pollSeconds = 10, $visibilitySeconds = 60)
    {
        try {
            $res = $this->queue->basic_get($this->name,true);
            if (is_null($res)) {
                return null;
            }
            $res = new ResMsg(['msg' => $res]);
            $this->trigger(self::EVENT_QUEUE_BEFORE_READ, MnsEvent::create(MnsEvent::QUEUE, $this->name, MnsEvent::STATUS_PRE, null, $res));
            $messageBody = $res->getResBody();
            return $messageBody;
        } catch (\ErrorException $e) {
            Yii::error("消息接收错误({$e->getCode()}): {$e->getFile()}\n{$e->getMessage()}\n{$e->getTraceAsString()}", 'rabbitmq.receive');
        }
        return null;
    }

    public function receive($waitSeconds = 10)
    {
        try {
            $res = $this->queue->basic_get($this->name,true);
            if (is_null($res)) {
                return null;
            }
            $res = new ResMsg(['msg' => $res]);
            $this->trigger(self::EVENT_QUEUE_BEFORE_READ, MnsEvent::create(MnsEvent::QUEUE, $this->name, MnsEvent::STATUS_PRE, null, $res));
            $messageBody = $res->getResBody();
            sleep($waitSeconds);
            return $messageBody;
        } catch (\ErrorException $e) {
            Yii::error("消息接收错误({$e->getCode()}): {$e->getFile()}\n{$e->getMessage()}\n{$e->getTraceAsString()}", 'rabbitmq.receive');
        }
        return null;
    }

    /**
     * 批量接收消息
     * @param $waitSeconds 等待的秒数
     * @return string 消息内容
     * @throws \ErrorException
     */
    public function receiveBatch($numOfMessages = 16, $waitSeconds = 10)
    {
        throw new \ErrorException('rabbitmq暂不支持批量接受消息', '509');
    }
}
