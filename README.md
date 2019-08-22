# Admiral: A demonstration fleet management tool for Platform.sh

Admiral is an *incomplete* reference implementation for fleet management on Platform.sh using the [Platform.sh API](https://api.platform.sh/).  It is not a fully functional application, but provides a documented example of the core functionality of a fleet management tool.

> **While Admiral will be updated periodically, it is not intended as a deployable application as-is and will *not* be supported as such.  As is, it entirely lacks user authentication so deploying it in a publicly accessible fashion is a horribly bad idea.**

It has two recommended use cases:

1) Use as a reference and inspiration to develop your own fleet management tooling.  The code in this repository can then serve as a guilde line of the sort of operations that are needed and the error handling they will require.

2) Use as the starting point of building a custom application for fleet management.  In that case, you are welcome to fork the code and modify/enhance it as you need but should not expect to update it from this repository afterward.

## License and Contributing

All code in this repository is available under the MIT license.  See [`License.md`](license.md) for details.

Pull requests that add generally useful functionality may be accepted, but the goal is not to evolve Admiral into a complete application, just most of one.

## Installation

Although this application will happily run on Platform.sh as-is, it does not have to.  It can run anywhere that has access to a MySQL/MariaDB database and can issue REST commands against the Platform.sh API, including your local laptop.

1) Configure database credentials as needed for Symfony.  Consult the Symfony documentation for how to do so.  Alternatively, if running on Platform.sh this step is automated and not necessary.
2) Run Doctrine Migrations to create the database: `php bin/console doctrine:migrations:migrate`.
3) Set an environment variable for the Platform.sh API key, named `PLATFORMSH_CLI_TOKEN`.  (If running on Platform.sh, set a Platform.sh variable named `env:PLATFORMSH_CLI_TOKEN`.)  See the [Platform.sh documentation](https://docs.platform.sh/development/cli/api-tokens.html) for how to create an API key.
4) Ensure that the environment where Admiral is running has access to an SSH keypair that is also registered on the account associated with the API key.  Some of the Git commands Admiral runs (when using `CloneProjectCode` below) require SSH access.  If the shell already has it, that's fine. If not, you can specify the path to a private key file in Symfony's `config/services.yaml` file (not to be confused with the Platform.sh file of the same name) by binding it to the `$privateKeyFile` parameter, like so:

    ```yaml
    services:
        _defaults:
            bind:
                $repositoryParentDir: '%kernel.project_dir%/var/archetypes'
                $privateKeyFile: '~/.ssh/deploy_key'
    ```

5) Start the Symfony queue worker.  In the default configuration, Admiral uses a Doctrine-backed worker to handle the Message Bus that executes most commands.  It therefore needs to have the background worker process started.  Run `php bin/console messenger:consume async` and see the [Symfony Messagenger documentation](https://symfony.com/doc/current/messenger.html) for further details.  Note that if you are running this application on Platform.sh this step is handled automatically with a [worker container](https://docs.platform.sh/configuration/app/workers.html) and there is nothing you need to do.

Be aware that all projects created by this tool will be owned by the user associated with the API key.  For that reason using a fleet-specific user is recommended.

## Architecture

Admiral incorporates the [Platform.sh PHP Client](https://github.com/platformsh/platformsh-client-php), which is a simple wrapper around the Platform.sh API.  PHP implementations are encouraged to use that library.  Other languages may call the API directly or implement a similar library.

It also includes the [Symfony Bridge](https://github.com/platformsh/symfonyflex-bridge) to streamline running Symfony on Platform.sh.  If you are not running Admiral on Platform.sh then this library has no effect.

### Data model

Admiral defines two entity types: `Archetype` and `Project`.

* An Archetype is a template for projects.  It consists of an upstream Git repository, a branch name to use for updates, and the name of a [Source Operation](https://docs.platform.sh/configuration/app/source-operations.html) that will perform code updates.

* A Project has a one-to-one correspondence with a project on Platform.sh.  Only the title and project ID is stored locally.  The rest is pulled as needed from the Platform.sh API.  (Technically the region is also saved locally, but that's more a side effect of the creation process.)  A Project is always associated with a single Archetype from which it was created.

### Overall flow

Admiral uses the [EasyAdminBundle](https://symfony.com/doc/master/bundles/EasyAdminBundle/index.html) for the admin UI, available at `/admin` (the default path).  Consult its documentation for how the admin is configured.

The system defines additional "actions" on the Project entity type that may be triggered by the user.  All actions delegate their behavior to messages using the Symfony [`MessageBus`](https://symfony.com/doc/current/messenger.html).  That allows all actions to be easily centralized in [message handlers](src/MessageHandler) where they can be easily reused.  The MessageBus also supports using asynchronous transports (queues), which may be transparently configured.  If you will be managing a large number of projects then using this tool then configuring an asynchronous transport is recommended to avoid blocking the user interface.

It also defines two "batch actions" that trigger the same Message command as the single action, but for all selected Projects.

Additionally, the system also implements several Doctrine lifecycle events on both Projects and Archetypes.  With one exception these listeners also defer their behavior to the MessageBus for centralized handling.  The lone exception is Project creation.  When a Project is created, the request must block until the corresponding Platform.sh Project is also created so that its project ID is available to be recorded.  As a result creating a new Project record is not always particular fast, but that cannot be changed without a considerable amount of additional synchronization code.

### Commands

With the exception of project creation, all behavior is implemented through the MessageBus component's command bus.  If reimplementing this functionality yourself in another framework or another language, these correspond, approximately, to the actions you will need to replicate.

* [`InitializeProjectCode`](src/MessageHandler/InitalizeProjectCode.php) - After a Project is created, it must be initialized with code.  This Command uses the `initialize` API call to populate the Platform.sh Project with the code from the master branch of its Archetype.  Be aware, however, that this command begins a new Git history, so the project will *not* have a common Git history with its Archetype.
* [`CloneProjectCode`](src/MessageHandler/CloneProjectCode.php) - Alternatively, if a shared Git history is needed, this command demonstrates how to do so manually with a Git push.  It's a bit more involved and requires the extra SSH key setup above, but a shared Git history makes subsequent merge-based updates easier.
* [`SynchronizeProject`](src/MessageHandler/SynchronizeProject.php) - Certain data must be kept in sync between a Project in Admiral and a Project on Platform.sh.  Specifically, the Project title is editable from the management console and there are project-level variables that need to be defined on the Platform.sh Project based on its Archetype.  This command sets all such values.  It is triggered on Project creation, Project update, and Archetype update (for all Projects on the Archetype). 
* [`UpdateProject`](src/MessageHandler/UpdateProject.php) - The core of the process. The Update command will first ensure that a branch of the appropriate name (as defined by the Archetype) exists, and is up to date (using the Platform.sy `Sync` command).  It will then trigger the Archetype-specified Source Operation on that environment.  Actually updating code on that branch is the responsibility of the Source Operation itself.
* [`MergeUpdateProject`](src/MessageHandler/MergeUpdateProject.php) - If an update branch is available, and it has updated code relative to the `master` branch, it will trigger a `Merge` command to merge the updates to `master` and trigger a new deployment.  Otherwise it has no effect.
* [`BackupProduction`](src/MessageHandler/BackupProduction.php) - A simple demonstration.  This command triggers a Backup of the project's production/`master` branch.
* [`DeleteProject`](src/MessageHandler/DeleteProjectHandler.php) - When a Project is deleted in the management console it is also deleted on Platform.sh.  (Note: This is a very destructive operation with no undo command.  You may wish to lock this action down to selected users.)

In concept, virtually any Platform.sh API call or set of calls can be wrapped up into a Message command and exposed through the UI.  Whether a given task makes more sense to implement in a fleet UI or to have users follow links to the Project and perform them there is left as an exercise for the implementer.
