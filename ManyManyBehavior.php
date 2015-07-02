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
    public $attributes;

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
        foreach ($this->attributes as $attribute => $relation) {
            if (!is_null($this->_set[$attribute])) {

                // Model::deleteAll('id_fk = :id_key', [':id_fk' => $this->owner->id_key]);
                call_user_func([$relation['class'], 'deleteAll'],
                    "{$relation['fk']} = :key", [':key' => $this->owner->{$relation['key']}]);
            }
        }
    }

    /**
     * @param Event $event
     */
    public function afterSaveModel($event)
    {

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