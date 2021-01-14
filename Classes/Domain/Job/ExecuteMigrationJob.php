<?php
declare(strict_types=1);

namespace Netlogix\Migrations\JobQueue\Domain\Job;

use Flowpack\JobQueue\Common\Job\JobInterface;
use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use InvalidArgumentException;
use Neos\Flow\Log\ThrowableStorageInterface;
use Netlogix\Migrations\Domain\Service\MigrationService;
use Netlogix\Migrations\JobQueue\Domain\Model\AsyncMigration;
use Throwable;

class ExecuteMigrationJob implements JobInterface
{

    /**
     * @var MigrationService
     */
    private $migrationService;

    /**
     * @var string
     */
    private $migrationVersion;

    /**
     * @var string
     */
    private $direction;

    /**
     * @var AsyncMigration
     */
    private $migration;

    /**
     * @var ThrowableStorageInterface
     */
    private $throwableStorage;

    public function __construct(string $migrationVersion, string $direction)
    {
        $this->migrationVersion = $migrationVersion;
        $this->direction = $direction;

        if (!in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException(
                sprintf('Direction must be either "up" or "down", "%s" given!', $direction),
                1610632788
            );
        }
    }

    public function initializeObject(): void
    {
        $this->migration = $this->migrationService->getMigrationByVersion($this->migrationVersion);

        if (!$this->migration instanceof AsyncMigration) {
            throw new InvalidArgumentException(
                sprintf('The migration "%s" must be of type "%s"!', get_class($this->migration), AsyncMigration::class),
                1610632422
            );
        }
    }

    public function execute(QueueInterface $queue, Message $message): bool
    {
        try {
            switch ($this->direction) {
                case 'up':
                    $this->migration->up();
                    break;
                case 'down':
                    $this->migration->down();
                    break;
            }
        } catch (Throwable $t) {
            $this->throwableStorage->logThrowable($t);
        }

        return true;
    }

    public function getLabel(): string
    {
        return sprintf('Run async migration "%s" (Direction %s)', $this->migrationVersion, strtoupper($this->direction));
    }

    public function injectMigrationService(MigrationService $migrationService): void
    {
        $this->migrationService = $migrationService;
    }

    public function injectThrowableStorage(ThrowableStorageInterface $throwableStorage): void
    {
        $this->throwableStorage = $throwableStorage;
    }

}
