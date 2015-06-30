<?php

namespace mervick\adminlte\behaviors;

use Yii;
use yii\base\Event;
use yii\db\BaseActiveRecord;

/**
 * Class ManyManyBehavior
 * @package mervick\adminlte\behaviors
 */
class ManyManyBehavior extends AttributeBehavior
{
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
}