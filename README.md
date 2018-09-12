Yii2 RabbitMQ
===========================

Yii2-RabbitMQ 是RabbitMQ的封装


安装方法
-------

推荐的安装方式是通过[composer](http://getcomposer.org/download/).

手动执行

```
php composer.phar composer require teddymail/yii2-rabbitmq
```

或者添加

```
"teddymail/yii2-rabbitmq": "*"
```

到工程的 `composer.json` 文件


配置组件
-------

```php
'mns'=>[
    'class'=>'teddymail/yii2-rabbitmq/RabbitMQ',
    'user' => '',
    'passwd' => '',
    'url' => '192.168.1.3',
],
```


使用示例
-------

发送消息:

```php
\Yii::$app->mns->myqueue->send("test content");
```

接收消息:

```php
$content = \Yii::$app->mns->myqueue->receive();
echo $content, "\n";
```

批量发送消息:

```php
$contents = [
    'test content',
    'test content again',
];
\Yii::$app->mns->myqueue->sendBatch($contents);
```


