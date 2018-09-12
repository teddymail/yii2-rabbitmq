<?php
/**
 * RabbitMQApi 调用api帮助类
 * @author: yuekang<iyuekang@gmail.com>
 * @date 2018/9/10 上午11:41
 */

namespace teddymail\yii2rabbitmq;


use lspbupt\curl\CurlHttp;

class RabbitMQApi extends CurlHttp
{
    public $user = '';
    public $password = '';
    public $host = '';
    public $port = '15672';

    public function init()
    {
        //填入base认证信息
        $baseAuth = sprintf("Basic %s", base64_encode($this->user. ':'. $this->password));
        $this->setHeader('Authorization', $baseAuth);
        parent::init();
    }
}