<?php
declare(strict_types=1);

namespace App;

use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\PlatformClient as ParentClient;


class PlatformClient extends ParentClient
{

    public function __construct(ConnectorInterface $connector = null)
    {
        parent::__construct($connector);

        $this->getConnector()->setApiToken(getenv('PLATFORMSH_CLI_TOKEN'), 'exchange');
    }
}
