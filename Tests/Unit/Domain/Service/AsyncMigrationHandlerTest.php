<?php
declare(strict_types=1);

namespace Netlogix\Migrations\JobQueue\Tests\Unit\Domain\Service;

use Flowpack\JobQueue\Common\Job\JobManager;
use Generator;
use \InvalidArgumentException;
use Neos\Flow\Tests\UnitTestCase;
use Netlogix\Migrations\Domain\Model\DefaultMigration;
use Netlogix\Migrations\Domain\Model\Migration;
use Netlogix\Migrations\Domain\Service\VersionResolver;
use Netlogix\Migrations\JobQueue\Domain\Job\ExecuteMigrationJob;
use Netlogix\Migrations\JobQueue\Domain\Model\AsyncMigration;
use Netlogix\Migrations\JobQueue\Domain\Service\AsyncMigrationHandler;

class AsyncMigrationHandlerTest extends UnitTestCase
{

    /**
     * @test
     */
    public function It_can_execute_AsyncMigrations(): void
    {
        $asyncMigration = self::getMockBuilder(AsyncMigration::class)
            ->getMock();

        $handler = new AsyncMigrationHandler();

        self::assertTrue($handler->canExecute($asyncMigration));
    }

    /**
     * @test
     * @dataProvider provideInvalidMigrationClassNames
     */
    public function It_cant_execute_any_other_Migrations(string $className): void
    {
        $migration = self::getMockBuilder($className)
            ->getMock();

        $handler = new AsyncMigrationHandler();

        self::assertFalse($handler->canExecute($migration));
    }

    /**
     * @test
     */
    public function It_will_queue_a_job_to_the_configured_queue_using_the_configured_options(): void
    {
        $handler = new AsyncMigrationHandler();

        $handler->injectSettings([
            'queueName' => 'fooQueue',
            'queueOptions' => ['bar' => 'baz']
        ]);

        $migration = self::getMockBuilder(AsyncMigration::class)
            ->setMockClassName('Version20210114162911')
            ->getMock();

        $jobManager = self::getMockBuilder(JobManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $jobManager
            ->expects(self::once())
            ->method('queue')
            ->with(
                'fooQueue',
                self::anything(),
                ['bar' => 'baz']
            );

        $handler->injectJobManager($jobManager);
        $handler->injectVersionResolver(new VersionResolver());

        $handler->up($migration);
    }

    /**
     * @test
     * @dataProvider provideDirections
     */
    public function It_will_queue_a_job_with_the_proper_migration_version_and_direction(string $direction): void
    {
        $handler = new AsyncMigrationHandler();

        $handler->injectSettings([
            'queueName' => 'fooQueue'
        ]);

        $version = (string)rand(20210000000000, 20220000000000);

        $migration = self::getMockBuilder(AsyncMigration::class)
            ->setMockClassName('Version' . $version)
            ->getMock();

        $jobManager = self::getMockBuilder(JobManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $expectedJob = new ExecuteMigrationJob(
            $version,
            $direction
        );

        $jobManager
            ->expects(self::once())
            ->method('queue')
            ->with(
                'fooQueue',
                $expectedJob,
                []
            );

        $handler->injectJobManager($jobManager);
        $handler->injectVersionResolver(new VersionResolver());

        $handler->{$direction}($migration);
    }

    /**
     * @test
     */
    public function If_no_queueName_is_set_an_exception_is_thrown(): void
    {
        $handler = new AsyncMigrationHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->injectSettings(['anything' => 'but the queue name']);
    }

    public static function provideDirections(): Generator
    {
        yield 'UP' => ['up'];
        yield 'DOWN' => ['down'];
    }

    public static function provideInvalidMigrationClassNames(): \Generator
    {
        yield DefaultMigration::class => [DefaultMigration::class];
        yield Migration::class => [Migration::class];
    }

}
