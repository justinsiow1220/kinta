<?php

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\DatabaseSafety;

/** @internal */
final class DatabaseConnectionSafetyTest extends CIUnitTestCase
{
    public function testAutomatedTestsUseTheConfiguredIsolatedMySqlDatabase(): void
    {
        $verified = DatabaseSafety::verify();

        self::assertSame('testing', ENVIRONMENT);
        self::assertNotSame($verified['defaultDatabase'], $verified['database']);
        self::assertStringContainsStringIgnoringCase('test', $verified['database']);
    }
}
