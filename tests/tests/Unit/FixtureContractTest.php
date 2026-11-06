<?php

declare(strict_types=1);

namespace LowWidontTests;

require_once dirname(__DIR__, 2) . '/support/Facade.php';

final class FixtureContractTest extends TestCase
{
    /**
     * Preserve an explicitly registered null service in the authored facade fixture.
     *
     * @return void
     */
    public function testNullFacadeServiceRemainsRegistered(): void
    {
        $facade = new Facade();
        $facade->set('fixture', null);
        $this->assertTrue($facade->has('fixture'));
        $this->assertNull($facade->get('fixture'));
    }
}
