<?php

namespace mervick\adminlte\behaviors;

use Yii;
use yii\base\Event;
use yii\db\BaseActiveRecord;
use yii\base\Behavior;

/**
 * Class ManyManyBehavior
 * @package mervick\adminlte\behaviors
 */
class ManyManyBehavior extends Behavior
{
    /**
     * @var array
     */
    public $attributes;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty($this->attributes)) {
            $this->attributes = [
                BaseActiveRecord::EVENT_BEFORE_INSERT => [$this->createdByAttribute, $this->updatedByAttribute],
                BaseActiveRecord::EVENT_BEFORE_UPDATE => $this->updatedByAttribute,
            ];
        }
    }

    /**
     * @param $attribute
     */
    public function validateManyMany($attribute)
    {
        \ChromePhp::log($attribute);
        if (is_array($this->_idChannels)) {
            foreach ($this->_idChannels as $id) {
                if (intval($id) != $id) {
                    $this->addError('idChannels', 'Items of Channels must be integers.');
                    break;
                }
            }
        } elseif (!empty($this->_idChannels)) {
            $this->addError('idChannels', 'Channels must be an array.');
        }
    }



    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->attributes) || parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->attributes)) {

        } else {
            parent::__set($name, $value);
        }
    }
}