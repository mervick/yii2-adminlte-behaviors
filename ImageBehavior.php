<?php

namespace mervick\adminlte\behaviors;

use Yii;
use yii\base\Event;
use yii\db\BaseActiveRecord;
use yii\base\Behavior;
use yii\base\UnknownMethodException;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
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
    public $domain;

    /**
     * @var string
     */
    public $upload_dir;

    /**
     * @var string
     */
    public $schema;

    /**
     * @var array
     */
    public $attributes;

    /**
     * Default images settings
     * @var array
     */
    protected $defaultImagesSettings = [
        'format' => 'jpg',
        'quality' => 85,
        'master' => 'adapt',
        'background' => '#fff',
    ];


    /**
     * @inheritdoc
     */
    public function events()
    {
        if (empty($this->attributes) && is_array($this->attributes)) {
            return [
                BaseActiveRecord::EVENT_BEFORE_INSERT => 'uploadImages',
                BaseActiveRecord::EVENT_BEFORE_UPDATE => 'uploadImages',
            ];
        }
        return [];
    }

    /**
     * Upload images.
     * @param array $changedAttributes
     * @throws \yii\base\ErrorException
     */
    protected function uploadImages($changedAttributes = [])
    {
        if (!empty($_FILES)) {
            $imageAttributes = array_diff(['avatar'], $changedAttributes);
            if (!empty($imageAttributes)) {
                $save = false;
                $modelName = array_reverse(explode('\\', self::className()))[0];
                foreach ($imageAttributes as $attribute) {
                    if (!empty($_FILES[$modelName]['tmp_name'][$attribute])) {
                        $upload_dir = Yii::getAlias($this->{"{$attribute}_upload_dir"});
                        $filename = $this->id . '-' . Yii::$app->security->generateRandomString(mt_rand(5, 12)) . '.jpg';
                        $old_filename = $this->$attribute;
                        $save = true;
                        foreach ($this->{"{$attribute}_sizes"} as $size) {
                            /* @var $im \mervick\image\Component */
                            $im = Yii::$app->image;
                            if ($image = $im->load($_FILES[$modelName]['tmp_name'][$attribute])) {
                                $sizes = explode('x', $size);
                                $path = "$upload_dir/$size";
                                if (!is_dir($path)) {
                                    @mkdir($path, 0777, true);
                                }
                                if (!empty($old_filename)) {
                                    @unlink("$path/$old_filename");
                                }
                                $image->resize($sizes[0], $sizes[1], 'crop')->background('#fff');
                                if ($image->save("$path/$filename", 85)) {
                                    $this->$attribute = $filename;
                                } else {
                                    $this->$attribute = '';
                                }
                            }
                        }
                        unset($_FILES[$modelName]['tmp_name'][$attribute]);
                    }
                }
                if ($save) {
                    $this->save(false);
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
    protected function getSizeIndex($attribute, $size)
    {
        $sizes = $this->attributes[$attribute];

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
            if ($arr['size'] === $size) {
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
     * @param $attribute
     * @param $size
     * @return string
     */
    protected function imageUrl($attribute, $size)
    {
        if (is_array($this->attributes[$attribute])) {
            $size = $this->getSizeIndex($attribute, $size);
            $host = rtrim(preg_replace('~^((?:https?\:)?//)(.*)$~',
                '\\1' . str_replace('{$domain}', '\\2', $this->domain),
                Yii::$app->urlManager->hostInfo), '/');

            return str_replace(['{$path}', '{$attribute}', '{$size}'],
                [$host, $attribute, $this->attributes[$attribute][$size]], $this->schema);
        }

        throw new UnknownMethodException(
            sprintf('Calling unknown method: %s::get%sUrl()', get_class($this), ucfirst($attribute)));
    }

    /**
     * @param null $size
     * @return string
     */
    public function getImgUrl($size = null)
    {
        return $this->imageUrl('img', $size);
    }

    /**
     * @param null $size
     * @return string
     */
    public function getImageUrl($size = null)
    {
        return $this->imageUrl('image', $size);
    }

    /**
     * @param null $size
     * @return string
     */
    public function getLogoUrl($size = null)
    {
        return $this->imageUrl('logo', $size);
    }

    /**
     * @param null $size
     * @return string
     */
    public function getAvatarUrl($size = null)
    {
        return $this->imageUrl('avatar', $size);
    }

    /**
     * @param null $size
     * @return string
     */
    public function getPictureUrl($size = null)
    {
        return $this->imageUrl('picture', $size);
    }

}