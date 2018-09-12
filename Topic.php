<?php
namespace teddymail\yii2rabbitmq;

use yii\base\Component;
use PhpAmqpLib\Message\AMQPMessage;

class Topic extends Component
{
    const EVENT_TOPIC_BEFORE_SEND = 'event_topic_before_send';
    const EVENT_TOPIC_AFTER_SEND = 'event_topic_after_send';

    public $client;
    public $name;

    private $topic;

    public function init()
    {
        parent::init();
        //建立topic
        $this->client->exchange_declare($this->name, 'topic', false, true,false);
        $this->topic = $this->client;
    }

    public function __call($method_name, $args)
    {
        if (method_exists($this->topic, $method_name)) {
            return call_user_func_array([$this->topic, $method_name], $args);
        } else {
            return parent::__call($method_name, $args);
        }
    }

    public function getTopicName()
    {
        return $this->name;
    }

    public function publish($content, $baseEncode = TRUE)
    {
        $message = new AMQPMessage($content);
        $event = MnsEvent::create(MnsEvent::TOPIC, $this->name, MnsEvent::STATUS_PRE, $message);
        $this->trigger(self::EVENT_TOPIC_BEFORE_SEND, $event);
        try {
            $this->topic->basic_publish($message, $this->name, '');
            $event->status = MnsEvent::STATUS_SEND;
            $this->trigger(self::EVENT_TOPIC_AFTER_SEND, $event);
            return true;
        }catch (\ErrorException $e) {
            $event->status = MnsEvent::STATUS_FAIL;
            $this->trigger(self::EVENT_TOPIC_AFTER_SEND, $event);
            return false;
        }
    }
}
