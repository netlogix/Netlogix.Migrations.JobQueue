# Netlogix.Migrations.JobQueue

## About Netlogix Migrations

This package provides the ability to run migrations of the `netlogix/migrations` package ([Netlogix.Migrations](https://github.com/netlogix/Netlogix.Migrations)) asynchronously. 

## Installation

`composer require netlogix/migrations-jobqueue`

## Configuration

To run a migration asynchronously, the Queue to be used needs to be defined:

```yaml
Netlogix:
  Migrations:
    JobQueue:
      queueName: 'nlx-migrations'
```

The queue (In this example `nlx-migrations`) must be configured in `Flowpack.JobQueue.Common` ([Check Github for more info](https://github.com/Flowpack/jobqueue-common))!


## Usage

Simply use the `AsyncMigration` interface in your migration:
```php
<?php
declare(strict_types=1);

namespace Netlogix\Migrations\Persistence\Migrations;

use Netlogix\Migrations\JobQueue\Domain\Model\AsyncMigration;

class Version20210114172342 implements AsyncMigration
{

    public function up(): void
    {
        // ...
    }

    public function down(): void
    {
        // ...
    }

}
```

When `./flow migrations:migrate` is run, the migration will instantly be marked as executed and an asynchronous job will be queued to the configured jobqueue.
