<?php
declare(strict_types=1);

namespace Netlogix\Migrations\JobQueue\Tests\Unit\Domain\Job;

use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use Generator;
use \InvalidArgumentException;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Tests\UnitTestCase;
use Netlogix\Migrations\Domain\Model\DefaultMigration;
use Netlogix\Migrations\Domain\Service\MigrationService;
use Netlogix\Migrations\Error\UnknownMigration;
use Netlogix\Migrations\JobQueue\Domain\Job\ExecuteMigrationJob;
use Netlogix\Migrations\JobQueue\Domain\Model\AsyncMigration;
use RuntimeException;

class ExecuteMigrationJobTest extends UnitTestCase
{

    /**
     * @var MigrationService|\PHPUnit_Framework_MockObject_MockObject
     */
    private $migrationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationService = $this
            ->getMockBuilder(MigrationService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @test
     * @dataProvider provideDirections
     */
    public function Up_and_down_are_allowed_for_direction(string $direction): void
    {
        $job = new ExecuteMigrationJob('foo', $direction);
        $this->assertInstanceOf(ExecuteMigrationJob::class, $job);
    }

    /**
     * @test
     */
    public function Other_values_are_disallowed_for_direction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExecuteMigrationJob('foo', 'bar');
    }

    /**
     * @test
     */
    public function If_an_unknown_migration_is_given_an_exception_is_thrown(): void
    {
        $job = new ExecuteMigrationJob('foo', 'up');
        $job->injectMigrationService($this->migrationService);

        $this->migrationService
            ->expects($this->once())
            ->method('getMigrationByVersion')
            ->with('foo')
            ->willThrowException(new UnknownMigration());

        $this->expectException(UnknownMigration::class);

        $job->initializeObject();

    }

    /**
     * @test
     */
    public function If_the_given_migration_is_not_of_type_AsyncMigration_an_exception_is_thrown(): void
    {
        $job = new ExecuteMigrationJob('foo', 'up');
        $job->injectMigrationService($this->migrationService);

        $this->migrationService
            ->expects($this->once())
            ->method('getMigrationByVersion')
            ->with('foo')
            ->willReturn(new class implements DefaultMigration {
                public function up(): void
                {
                }

                public function down(): void
                {
                }
            });

        $this->expectException(InvalidArgumentException::class);

        $job->initializeObject();
    }

    /**
     * @test
     * @dataProvider provideDirections
     */
    public function The_migration_method_matching_the_given_direction_is_called(string $direction): void
    {
        $job = new ExecuteMigrationJob('foo', $direction);
        $job->injectMigrationService($this->migrationService);

        $asyncMigration = $this->setupAsyncMigrationMock();

        $job->initializeObject();

        $asyncMigration
            ->expects($this->once())
            ->method($direction);

        $this->executeJob($job);
    }

    /**
     * @test
     */
    public function If_the_migration_throws_an_exception_it_is_logged(): void
    {
        $exception = new RuntimeException('foo', 1610636878);

        $throwableStorage = $this->getMockBuilder(ThrowableStorageInterface::class)
            ->getMock();

        $throwableStorage
            ->expects($this->once())
            ->method('logThrowable')
            ->with($exception);

        $job = new ExecuteMigrationJob('foo', 'up');
        $job->injectMigrationService($this->migrationService);
        $job->injectThrowableStorage($throwableStorage);

        $asyncMigration = $this->setupAsyncMigrationMock();

        $job->initializeObject();

        $asyncMigration
            ->expects($this->once())
            ->method('up')
            ->willThrowException($exception);

        $this->executeJob($job);
    }

    /**
     * @test
     */
    public function The_execute_method_always_returns_true(): void
    {
        $exception = new RuntimeException('foo', 1610636878);

        $throwableStorage = $this->getMockBuilder(ThrowableStorageInterface::class)
            ->getMock();

        $job = new ExecuteMigrationJob('foo', 'up');
        $job->injectMigrationService($this->migrationService);
        $job->injectThrowableStorage($throwableStorage);

        $asyncMigration = $this->setupAsyncMigrationMock();

        $job->initializeObject();

        $asyncMigration
            ->expects($this->once())
            ->method('up')
            ->willThrowException($exception);

        $this->assertTrue($this->executeJob($job));
    }

    /**
     * @test
     * @dataProvider provideDirections
     */
    public function The_label_contains_the_migration_version_and_direction(string $direction): void
    {
        $job = new ExecuteMigrationJob('foo', $direction);
        $job->injectMigrationService($this->migrationService);

        $this->setupAsyncMigrationMock();

        $job->initializeObject();

        $this->assertEquals(sprintf('Run async migration "foo" (Direction %s)', strtoupper($direction)), $job->getLabel());
    }

    public function provideDirections(): Generator
    {
        yield 'UP' => ['up'];
        yield 'DOWN' => ['down'];
    }

    private function executeJob(ExecuteMigrationJob $job): bool
    {
        $queue = $this->getMockBuilder(QueueInterface::class)
            ->getMock();
        $message = $this->getMockBuilder(Message::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $job->execute($queue, $message);
    }

    /**
     * @return AsyncMigration|\PHPUnit_Framework_MockObject_MockObject
     */
    private function setupAsyncMigrationMock()
    {
        $asyncMigration = $this->getMockBuilder(AsyncMigration::class)
            ->getMock();

        $this->migrationService
            ->method('getMigrationByVersion')
            ->willReturn($asyncMigration);

        return $asyncMigration;
    }

}
