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

    private $_set = [];


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
     * Get label for attribute
     * @param string $attribute
     * @return string
     */
    protected function label($attribute)
    {
        return isset($this->attributes[$attribute]['label']) ?
            $this->attributes[$attribute]['label'] : $attribute;
    }

    /**
     * @param $attribute
     */
    public function validateManyMany($attribute)
    {
        if (is_array($this->_set[$attribute])) {
            foreach ($this->_set[$attribute] as $id) {
                if (intval($id) != $id) {
                    $this->addError($attribute, sprintf('Items of %s must be integers.', $this->label($attribute)));
                    break;
                }
            }
        } elseif (!empty($this->_idChannels)) {
            $this->addError($attribute, sprintf('%s must be an array.', $this->label($attribute)));
        }
    }



    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return !empty($this->attributes[$name]) || parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (!empty($this->attributes[$name])) {
            $this->_set[$name] = [];
            if (!empty($value)) {
                foreach ($value as $id) {
                    $this->_set[] = $id;
                }
            }
        } else {
            parent::__set($name, $value);
        }
    }
}