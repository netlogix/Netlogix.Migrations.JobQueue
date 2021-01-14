<?php
declare(strict_types=1);

namespace Netlogix\Migrations\JobQueue\Domain\Service;

use Flowpack\JobQueue\Common\Job\JobManager;
use InvalidArgumentException;
use Neos\Utility\TypeHandling;
use Netlogix\Migrations\Domain\Handler\DefaultMigrationHandler;
use Netlogix\Migrations\Domain\Model\Migration;
use Netlogix\Migrations\Domain\Service\VersionResolver;
use Netlogix\Migrations\JobQueue\Domain\Job\ExecuteMigrationJob;
use Netlogix\Migrations\JobQueue\Domain\Model\AsyncMigration;

final class AsyncMigrationHandler extends DefaultMigrationHandler
{

    /**
     * @var JobManager
     */
    protected $jobManager;

    /**
     * @var VersionResolver
     */
    protected $versionResolver;

    /**
     * @var string
     */
    private $queueName;

    /**
     * @var array
     */
    private $queueOptions;

    public function canExecute(Migration $migration): bool
    {
        return $migration instanceof AsyncMigration;
    }

    public function up(Migration $migration): void
    {
        assert($migration instanceof AsyncMigration);

        $this->queueJob($migration, 'up');
    }

    public function down(Migration $migration): void
    {
        assert($migration instanceof AsyncMigration);

        $this->queueJob($migration, 'down');
    }

    public function injectJobManager(JobManager $jobManager): void
    {
        $this->jobManager = $jobManager;
    }

    public function injectVersionResolver(VersionResolver $versionResolver): void
    {
        $this->versionResolver = $versionResolver;
    }

    public function injectSettings(array $settings): void
    {
        if (!array_key_exists('queueName', $settings)) {
            throw new InvalidArgumentException('No "queueName" given for async migrations!', 1610632142);
        }

        $this->queueName = $settings['queueName'];
        $this->queueOptions = $settings['queueOptions'] ?? [];
    }

    protected function queueJob(AsyncMigration $migration, string $direction): void
    {
        $migrationClassName = TypeHandling::getTypeForValue($migration);
        $versionVersion = $this->versionResolver->extractVersion($migrationClassName);

        $job = new ExecuteMigrationJob(
            $versionVersion,
            $direction
        );

        $this->jobManager->queue($this->queueName, $job, $this->queueOptions);
    }

}
