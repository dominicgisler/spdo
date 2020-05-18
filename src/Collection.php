<?php

namespace Gisler\Spdo;

use ArrayObject;

/**
 * Class Collection
 * @package Gisler\Spdo
 */
class Collection extends ArrayObject
{
    /**
     * @param array $input
     */
    public function __construct(array $input = [])
    {
        parent::__construct($input, ArrayObject::STD_PROP_LIST);
    }
}
