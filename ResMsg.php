<?php

namespace teddymail\yii2rabbitmq;

use yii\base\Component;
use yii\base\InvalidValueException;

/**
 * Class ResMsg.
 *
 * @property $resBody
 */
class ResMsg extends Component
{
    public $msg;

    private $_resBody;

    public function init()
    {
        parent::init();
        if (!$this->msg || !method_exists($this->msg, 'getBody')) {
            throw new InvalidValueException('Property "msg" should has method getBody');
        }
    }

    public function __call($name, $params)
    {
        if (method_exists($this->msg, $name)) {
            return call_user_func_array([$this->msg, $name], $params);
        } else {
            return parent::__call($name, $params);
        }
    }

    public function setResBody($value)
    {
        $this->_resBody = $value;
    }

    public function getResBody()
    {
        if ($this->_resBody === null) {
            $this->_resBody = $this->getBody();
        }
        return $this->_resBody;
    }
}
