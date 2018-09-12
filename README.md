Yii2 RabbitMQ
===========================

Yii2-RabbitMQ 是RabbitMQ的封装


安装方法
-------

推荐的安装方式是通过[composer](http://getcomposer.org/download/).

手动执行

```
php composer.phar require koenigseggposche/yii2mns
```

或者添加

```
"koenigseggposche/yii2mns": "*"
```

到工程的 `composer.json` 文件


配置组件
-------

```php
'mns'=>[
    'class'=>'koenigseggposche\yii2mns\Mns',
    'accessId' => '',
    'accessKey' => '',
    'endpoint' => 'http://.mns.cn-beijing.aliyuncs.com/',
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

批量接收消息:

```php
$contents = \Yii::$app->mns->myqueue->receiveBatch(100);
foreach ($contents as $content) {
    echo $content, "\n";
}
```
