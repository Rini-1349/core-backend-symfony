<?php 

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ActionMetadata
{
    public function __construct(private ?string $alias, private ?string $description = null) {

    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
