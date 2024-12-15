<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AccessMethods
{
    public array $readMethods;
    public array $writeMethods;

    public function __construct(array $readMethods = [], array $writeMethods = [])
    {
        $this->readMethods = $readMethods;
        $this->writeMethods = $writeMethods;
    }
}
