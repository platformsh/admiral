<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Command message to indicate a project needs to have its update command run.
 */
class UpdateProject
{
    use ProjectMessage;
}
