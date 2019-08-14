<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Command message indicating a project's master environment should be backed up.
 */
class BackupProduction
{
    use ProjectMessage;
}
