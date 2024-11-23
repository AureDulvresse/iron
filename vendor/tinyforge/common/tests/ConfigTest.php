<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use TinyForge\Common\Config;

class ConfigTest extends TestCase
{
    public function testLoadAndGetConfig()
    {
        Config::load(__DIR__ . '/fixtures/config.php');
        $this->assertEquals('localhost', Config::get('database.host'));
    }

    public function testDefaultValue()
    {
        $this->assertEquals('default', Config::get('nonexistent.key', 'default'));
    }
}
