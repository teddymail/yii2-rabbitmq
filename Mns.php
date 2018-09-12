<?php
namespace teddymail\yii2rabbitmq;

use Yii;
use yii\base\Component;
use AliyunMNS\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
class Mns extends Component
{
    public $user = '';
    public $passwd = '';
    public $url = '';
    public $port = '5672';

    private $client = null;
    private $queues = [];
    private $topics = [];

    public function init()
    {
        parent::init();
        //开启连接
        $connection = new AMQPStreamConnection($this->url, $this->port, $this->user, $this->passwd);
        //打开信道
        $this->client = $connection->channel();

    }

    public function __call($method_name, $args)
    {
        if (method_exists($this->client, $method_name)) {
            return call_user_func_array([$this->client, $method_name], $args);
        } else {
            return parent::__call($method_name, $args);
        }
    }

    public function __get($name)
    {
        $ret = null;
        if (substr($name, -5) == 'Topic') {
            $name = substr($name, 0, strlen($name)-5);
            if (isset($this->topics[$name])) {
                return $this->topics[$name];
            }
            $params = [
                'class' => Topic::className(),
                'client' => $this->client,
                'name' => $name,
            ];
            $ret = Yii::createObject($params);
            $this->topics[$name] = $ret;
        } else {
            if (isset($this->queues[$name])) {
                return $this->queues[$name];
            }
            $params = [
                'class' => Queue::className(),
                'client' => $this->client,
                'name' => $name,
            ];
            $ret = Yii::createObject($params);
            $this->queues[$name] = $ret;
        }
        return $ret;
    }
}
