<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Command message to indicate a project needs to have its update branch merged.
 */
class MergeUpdateProject
{
    use ProjectMessage;
}
