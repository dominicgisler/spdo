<?php

namespace Gisler\Spdo;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Class AbstractModel
 * @package Gisler\Spdo
 */
abstract class AbstractModel
{
    /**
     * @var array
     */
    private $props;

    /**
     * @param array $input
     * @throws ReflectionException
     */
    public function __construct(array $input = [])
    {
        $this->exchangeArray($input);
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getArrayCopy(): array
    {
        $array = [];
        foreach ($this->getProps() as $prop) {
            $array[$prop] = $this->{$prop};
        }
        return $array;
    }

    /**
     * @param array $array
     * @return array
     * @throws ReflectionException
     */
    public function exchangeArray(array $array): array
    {
        $old = $this->getArrayCopy();

        foreach ($array as $prop => $value) {
            if (in_array($prop, $this->getProps())) {
                $this->{$prop} = $value;
            }
        }
        return $old;
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    private function getProps(): array
    {
        if ($this->props) {
            return $this->props;
        }

        $this->props = [];
        $refl = new ReflectionClass($this);
        foreach ($refl->getProperties(ReflectionProperty::IS_PUBLIC) as $item) {
            $this->props[] = $item->getName();
        }

        return $this->props;
    }
}
