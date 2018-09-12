<?php
namespace teddymail\yii2rabbitmq;

use yii\base\Event;

/**
 * Class MnsEvent
 *
 * @property \AliyunMNS\Requests\BaseRequest $request
 * @property ResMsg $msg
 *
 * @package koenigseggposche\yii2mns
 */
class MnsEvent extends Event
{
    const QUEUE = 'queue';
    const TOPIC = 'topic';

    const STATUS_PRE = 0;
    const STATUS_SEND = 1;
    const STATUS_FAIL = 2;

    public $type;
    public $title;
    public $status;
    public $request;
    public $response;
    
    public static function create($type, $title, $status, $request = null, $response = null) {
        return new static(compact('type', 'title', 'status', 'request', 'response'));
    }
}
