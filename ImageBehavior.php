<?php

namespace mervick\adminlte\behaviors;

use Yii;
use yii\base\ErrorException;
use yii\base\Event;
use yii\db\BaseActiveRecord;
use yii\base\Behavior;
use yii\base\UnknownMethodException;
use mervick\image\Image;


/**
 * Class ImageBehavior
 * @package mervick\adminlte\behaviors
 */
class ImageBehavior extends Behavior
{
    /**
     * @var string
     */
    public $imageDriver = 'mervick\\image\\drivers\\Imagick';

    /**
     * @var string
     */
    public $domain = 'img.{$domain}';

    /**
     * @var string
     */
    public $upload_dir = '@images';

    /**
     * @var string
     */
    public $schema = '{$path}/{$model}/{$attribute}/{$size}';

    /**
     * @var array
     */
    public $attributes = [];

    /**
     * Default images settings
     * @var array
     */
    protected $defaultImagesSettings = [
        'format' => 'jpg',
        'quality' => 85,
        'size' => 'original',
        'master' => 'adapt',
        'background' => '#fff',
    ];


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'uploadImages',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'uploadImages',
        ];
    }

    /**
     * Upload the images.
     * @param Event $event
     * @throws ErrorException
     */
    public function uploadImages($event)
    {
        if (!empty($this->attributes))
        {
            $modelName = array_reverse(explode('\\', $this->owner->className()))[0];

            if (!empty($_FILES[$modelName]['tmp_name']))
            {
                $save = false;
                $files = &$_FILES[$modelName]['tmp_name'];

                if (!$this->owner->hasMethod('getPrimaryKey', false)) {
                    throw new ErrorException(
                        sprintf('Cannot get primary key of class %s', $this->owner->className()));
                }

                /** @var int $primaryKey */
                $primaryKey = call_user_func([$this->owner, 'getPrimaryKey']);
                $upload_dir = Yii::getAlias($this->upload_dir, true);

                foreach ($this->attributes as $attribute => $settings)
                {
                    if (!empty($files[$attribute]))
                    {
                        $save = true;
                        $settings = array_merge($this->defaultImagesSettings, $settings);
                        $filename = $primaryKey . '-' . Yii::$app->security->generateRandomString(mt_rand(5, 12));

                        if (!in_array($attribute, $this->owner->attributes())) {
                            throw new ErrorException(
                                sprintf('Cannot get attribute %s of class %s', $attribute, $this->owner->className()));
                        }

                        foreach ($settings['sizes'] as $name => $options)
                        {
                            if (is_array($options)) {
                                $options = array_merge($settings, $options);
                            } else {
                                $options = array_merge($settings, ['size' => $options]);
                            }

                            if ($image = Image::load($files[$attribute], $this->imageDriver))
                            {
                                $path = $this->schemaTo($upload_dir, $attribute, $name);

                                if (!empty($this->owner->$attribute)) {
                                    @unlink("$path/{$this->owner->$attribute}.{$options['format']}");
                                }

                                if ($options['size'] !== 'original') {
                                    $info = explode('x', strtolower($options['size']));
                                    $width = empty($info[0]) ? null: $info[0];
                                    $height = empty($info[1]) ? null: $info[1];
                                    $image->resize($width, $height, $options['master'])
                                        ->background($options['background']);
                                }

                                if ($image->save("$path/$filename.{$options['format']}", $options['quality'])) {
                                    $this->owner->$attribute = $filename;
                                } else {
                                    $this->owner->$attribute = '';
                                }
                            }
                        }

                        unset($files[$attribute]);
                    }
                }

                if ($save && $event->name === BaseActiveRecord::EVENT_AFTER_UPDATE) {
                    $this->owner->save(false);
                }
            }
        }
    }

    /**
     * Get image size index
     * @param string $attribute
     * @param string|null $size
     * @return int|string
     */
    protected function sizeIndex($attribute, $size)
    {
        $sizes = $this->attributes[$attribute]['sizes'];

        if (isset($sizes[$size])) {
            return $size;
        }

        if (($count = count($sizes)) === 1) {
            return array_keys($sizes)[0];
        }

        if (empty($size)) {
            return !isset($sizes['default']) ? !isset($sizes['normal']) ? array_keys($sizes)[0]  : 'normal' : 'default';
        }

        foreach ($sizes as $key => $arr) {
            if ($arr === $size || $arr['size'] === $size) {
                return $key;
            }
        }

        $keys = array_keys($sizes);

        if (in_array($size, ['big', 'large'])) {
            return $keys[$count-1];
        }

        if (in_array($size, ['medium', 'normal'])) {
            return $keys[floor($count/2) - 1];
        }

        return $keys[0];
    }

    /**
     * @param string $path
     * @param string $attribute
     * @param string $size
     * @return string
     */
    protected function schemaTo($path, $attribute, $size)
    {
        return str_replace(['{$path}', '{$model}', '{$attribute}', '{$size}'],
            [rtrim($path, '/'), array_reverse(explode('\\', $this->owner->className()))[0], $attribute, $size],
            $this->schema);
    }

    /**
     * Get image url for attribute with custom size
     * @param string $attribute
     * @param string $size
     * @return string|null
     */
    protected function imageUrl($attribute, $size = null)
    {
        if (in_array($attribute, $this->owner->attributes()) && is_array($this->attributes[$attribute]))
        {
            if (empty($this->owner->$attribute)) {
                return null;
            }

            $size = $this->sizeIndex($attribute, $size);
            $host = preg_replace('~^((?:https?\:)?//)(.*)$~',
                '\\1' . str_replace('{$domain}', '\\2', $this->domain),
                Yii::$app->urlManager->hostInfo);

            $format = array_merge($this->defaultImagesSettings, $this->attributes[$attribute],
                $this->attributes[$attribute]['sizes'][$size])['format'];

            return $this->schemaTo($host, $attribute, "$size/{$this->owner->$attribute}.$format");
        }

        throw new UnknownMethodException(
            sprintf('Calling unknown method: %s::get%sUrl()', get_class($this), ucfirst($attribute)));
    }

    /**
     * Get attribute from method `get{$Attribute}Url`
     * @param string $name
     * @return null|string
     */
    private function attributeFromGetMethodUrl($name)
    {
        return $this->attributeFromPropertyUrl(($pos = strpos($name, 'get')) === 0 ? lcfirst(substr($name, 3)) : null);
    }

    /**
     * Get attribute from property `{$attribute}Url`
     * @param string $name
     * @return null|string
     */
    private function attributeFromPropertyUrl($name)
    {
        return ($len = strlen($name)) > 3 && ($pos = strpos($name, 'Url', $len - 3)) !== false ? substr($name, 0, -3) : null;
    }

    /**
     * @inheritdoc
     */
    public function hasMethod($name)
    {
        $attribute = $this->attributeFromGetMethodUrl($name);
        return ($attribute && is_array($this->attributes[$attribute])) || parent::hasMethod($name);
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        $attribute = $this->attributeFromGetMethodUrl($name);

        if ($attribute && is_array($this->attributes[$attribute])) {
            array_unshift($params, $attribute);
            return call_user_func_array([$this, 'imageUrl'], $params);
        } else {
            return parent::__call($name, $params);
        }
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        $attribute = $this->attributeFromPropertyUrl($name);
        return ($attribute && is_array($this->attributes[$attribute])) || parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        $attribute = $this->attributeFromPropertyUrl($name);

        if ($attribute && is_array($this->attributes[$attribute])) {
            return $this->imageUrl($attribute);
        } else {
            parent::__get($name);
        }
    }
}