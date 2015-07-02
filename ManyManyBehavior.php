<?php

namespace mervick\adminlte\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\BaseActiveRecord;


/**
 * Class ManyManyBehavior
 * @package mervick\adminlte\behaviors
 */
class ManyManyBehavior extends Behavior
{
    /**
     * @var array
     */
    public $relations;

    /**
     * @var array
     */
    protected $_set = [];


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdateModel',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSaveModel',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSaveModel',
        ];
    }

    /**
     * @param Event $event
     */
    public function beforeUpdateModel($event)
    {
        foreach ($this->_set as $attribute) {
            $relation = $this->relations[$attribute];
            call_user_func([$relation['class'], 'deleteAll'],
                "{$relation['fk']} = :key", [':key' => $this->owner->{$relation['key']}]);
        }
    }

    /**
     * @param Event $event
     */
    public function afterSaveModel($event)
    {
        foreach ($this->_set as $attribute => $keys) {
            if (!empty($keys)) {
                $relation = $this->relations[$attribute];
                foreach ($keys as $id) {
                    $model = Yii::createObject($relation['class']);
                    $model->{$relation['fk']} = $this->owner->{$relation['key']};
                    $model->{$relation['many_fk']}  = $id;
                    $model->save();
                }
            }
        }
    }

    /**
     * Get label for attribute
     * @param string $attribute
     * @return string
     */
    protected function label($attribute)
    {
        return isset($this->relations[$attribute]['label']) ?
            $this->relations[$attribute]['label'] : $attribute;
    }

    /**
     * @param $attribute
     */
    public function validateManyMany($attribute)
    {
        if (is_array($this->_set[$attribute])) {
            foreach ($this->_set[$attribute] as $id) {
                if (!ctype_digit(strval($id))) {
                    $this->addError($attribute, sprintf('Items of %s must be integers.', $this->label($attribute)));
                    break;
                }
            }
        } elseif (!empty($this->_set[$attribute])) {
            $this->addError($attribute, sprintf('%s must be an array.', $this->label($attribute)));
        }
    }



    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return !empty($this->relations[$name]) || parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (!empty($this->relations[$name])) {
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