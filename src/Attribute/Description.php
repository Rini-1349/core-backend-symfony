<?php 

namespace App\Attribute;


#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class Description
{
    public function __construct(public string $text)
    {
    }
}

