<?php
declare(strict_types=1);


namespace  App\Git;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Abstraction for a cloning Git repository.
 *
 * @todo Verify that the handling of Git commands works on Psh and doesn't
 * need additional key handling, as that would be ugly.
 */
class Repository
{
    /**
     * The directory in which to create the repository.
     *
     * @var string
     */
    protected $repositoryParentDirectory;

    /**
     * The full Git URI (including .git suffix) of the archetype repo from which to clone.
     *
     * @var string
     */
    protected $upstreamUri;

    /**
     * The computed short name of the repository within the parent directory.
     *
     * @var string
     */
    protected $repositoryDirectoryName;

    /**
     * The full path on disk to the repository.
     *
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

    /**
     * Ensures that the repository directory is present and up to date.
     */
    public function ensureRepository() : void
    {
        $this->ensureRepositoryParent();

        if (file_exists($this->repositoryWorkingDir)) {
            $this->updateFromOrigin();
        } else {
            $this->gitClone($this->upstreamUri, $this->repositoryDirectoryName);
        }
    }

    /**
     * Ensures that the common parent directory of all repositories is created.
     *
     * @return bool
     */
    protected function ensureRepositoryParent() : bool
    {
        if (!file_exists($this->repositoryParentDirectory)) {
            return mkdir($this->repositoryParentDirectory, 0775, true);
        }
        return true;
    }

    /**
     * Adds a remote to the repository.
     *
     * @param string $name
     * @param string $remoteUri
     */
    public function addRemote(string $name, string $remoteUri)
    {
        $this->runGitCommandInRepo(['git', 'remote', 'add', $name, $remoteUri]);
    }

    /**
     * Pushes the current branch to a remote.
     *
     * The current branch should always be "master"; if it's not, something else
     * went wrong.  Better error handling around that is probably wise.
     *
     * @param string $name
     *   The name of the remote to which to push.
     * @param string $remoteBranch
     *   The remote branch to which to push.
     */
    public function pushToRemote(string $name, string $remoteBranch)
    {
        $this->runGitCommandInRepo(['git', 'push', $name, $remoteBranch]);
    }

    /**
     * Update code from all remotes.
     */
    protected function updateFromOrigin()
    {
        $this->runGitCommandInRepo(['git', 'pull', '--all']);
    }

    /**
     * Runs a provided Git command statement within the repository.
     *
     * Because Symfony's Process component wants the command as an array of elements,
     * this method takes the same format.  So ['git', 'pull'] would execute "git pull".
     *
     * @param array $command
     */
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

    /**
     * Clone a remote repository to the specified directory.
     *
     * This is the "initialize" command for a local repository.  It does not use
     * runGitCommandInRepo() because the repo directory doesn't exist yet, so
     * the working directory cannot be used yet.
     *
     * @param string $originUri
     *   The Git URI of the origin from which to clone.
     * @param string $directory
     *   The directory name within the parent to which to clone.
     */
    protected function gitClone(string $originUri, string $directory)
    {
        // We need a custom CWD for this one, as it's going to run without the repository already existing.
        $process = new Process(['git', 'clone', $originUri, $directory], $this->repositoryParentDirectory, [
            // Some but not all Git commands need SSH, so force it to use our key if so.
            // 'GIT_SSH_COMMAND' => "ssh -i {$this->privateKeyFilename}",
            // Some but not all Git commands will push to Platform.sh, and those should not wait.
            'PLATFORMSH_PUSH_NO_WAIT' => 1
        ]);

        $this->logger->debug('Running Git Command: {command}', ['command' => $process->getCommandLine()]);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            $this->logger->error("Process failed. Exit code: {exitCode} {message}", [
                'message' => $e->getMessage(),
                'exitCode' => $process->getExitCode(),
                'exception' => $e,
            ]);
        }

        if ($process->getExitCode()) {
            throw new \RuntimeException($process->getExitCodeText(), $process->getExitCode());
        }
    }
}
