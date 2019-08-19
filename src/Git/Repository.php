<?php
declare(strict_types=1);


namespace  App\Git;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

class Repository
{
    /**
     * @var string
     */
    protected $repositoryParentDirectory;

    /**
     * @var string
     */
    protected $upstreamUri;

    /**
     * @var string
     */
    protected $repositoryDirectoryName;

    /**
     * @var string
     */
    protected $repositoryWorkingDir;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(string $repositoryParentDirectory, string $upstreamUri, LoggerInterface $logger = null)
    {
        $this->repositoryParentDirectory = $repositoryParentDirectory;
        $this->upstreamUri = $upstreamUri;
        $this->logger = $logger ?? new NullLogger();

        $this->repositoryDirectoryName = basename(parse_url($this->upstreamUri, PHP_URL_PATH), '.git');

        $this->repositoryWorkingDir = $this->repositoryParentDirectory . '/' . $this->repositoryDirectoryName;
    }

    public function ensureRepository()
    {
        $this->ensureRepositoryParent();

        if (file_exists($this->repositoryWorkingDir)) {
            $this->updateFromOrigin();
        } else {
            $this->gitClone($this->upstreamUri, $this->repositoryDirectoryName);
        }
    }

    protected function ensureRepositoryParent()
    {
        if (!file_exists($this->repositoryParentDirectory)) {
            mkdir($this->repositoryParentDirectory, 0775, true);
        }
    }

    public function addRemote(string $name, string $remoteUri)
    {
        $this->runGitCommandInRepo(['git', 'remote', 'add', $name, $remoteUri]);
    }

    public function pushToRemote(string $name, string $remoteBranch)
    {
        $this->runGitCommandInRepo(['git', 'push', $name, $remoteBranch]);
    }

    protected function updateFromOrigin()
    {
        $this->runGitCommandInRepo(['git', 'pull', '--all']);
    }

    protected function runGitCommandInRepo(array $command)
    {
        $process = new Process($command, $this->repositoryWorkingDir, [
            // Some but not all Git commands need SSH, so force it to use our key if so.
            // 'GIT_SSH_COMMAND' => "ssh -i {$this->privateKeyFilename}",
            // Some but not all Git commands will push to Platform.sh, and those should not wait.
            'PLATFORMSH_PUSH_NO_WAIT' => 1
        ]);
        $process->start();

        $this->logger->debug('Running Git Command: {command}', ['command' => $process->getCommandLine()]);

        $process->wait();

        if ($process->getExitCode()) {
            throw new \RuntimeException($process->getExitCodeText(), $process->getExitCode());
        }
    }

    protected function gitClone(string $originUri, string $directory)
    {
        // We need a custom CWD for this one, as it's going to run without the repository already existing.
        $process = new Process(['git', 'clone', $originUri, $directory], $this->repositoryParentDirectory, [
            // Some but not all Git commands need SSH, so force it to use our key if so.
            // 'GIT_SSH_COMMAND' => "ssh -i {$this->privateKeyFilename}",
            // Some but not all Git commands will push to Platform.sh, and those should not wait.
            'PLATFORMSH_PUSH_NO_WAIT' => 1
        ]);
        $process->start();

        $this->logger->debug('Running Git Command: {command}', ['command' => $process->getCommandLine()]);

        $process->wait();

        if ($process->getExitCode()) {
            throw new \RuntimeException($process->getExitCodeText(), $process->getExitCode());
        }
    }
}
