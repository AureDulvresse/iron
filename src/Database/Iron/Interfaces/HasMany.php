<?php

namespace Forge\Database\Iron\Interfaces;

interface HasMany extends RelationshipInterface
{
    public function associate($model): void;
    public function dissociate(): void;
    public function getRelated(): mixed;
}
