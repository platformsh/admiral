<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Message\MergeUpdateProject;
use App\Message\UpdateProject;
use App\PlatformClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Controller for the Project entity's admin pages.
 *
 * This class lets us add additional actions and batch actions for the Project
 * entity specifically.  See the EasyAdminBundle documentation for more on how
 * that works.
 */
class ProjectController extends AdminController
{
    /**
     * @var PlatformClient
     */
    protected $client;

    /**
     * @var MessageBusInterface
     */
    protected $messageBus;

    public function __construct(PlatformClient $client, MessageBusInterface $messageBus)
    {
        $this->client = $client;
        $this->messageBus = $messageBus;
    }

    /**
     * Updates a list of projects' update branches.
     *
     * @param array $ids
     */
    public function updateBatchAction(array $ids)
    {
        foreach ($ids as $id) {
            $this->messageBus->dispatch(new UpdateProject((int)$id));
        }

        $this->addFlash('notice', sprintf('Update queued for %d projects.', count($ids)));
    }

    /**
     * Merge a list of projects' update branches.
     *
     * @param array $ids
     */
    public function merge_UpdateBatchAction(array $ids)
    {
        foreach ($ids as $id) {
            $this->messageBus->dispatch(new MergeUpdateProject((int)$id));
        }

        $this->addFlash('notice', sprintf('Merge queued for %d projects.', count($ids)));
    }

    /**
     * Updates a single project's update branch.
     *
     * @return RedirectResponse
     */
    public function updateAction()
    {
        $id = $this->request->query->get('id');

        $this->messageBus->dispatch(new UpdateProject((int)$id));

        $project = $this->em->getRepository(Project::class)->find($id);
        $this->addFlash('notice', sprintf('Update queued for project %s (%s)', $project->getTitle(), $project->getProjectId()));

        // Redirect to the 'list' view of the given entity.
        return $this->redirectToRoute('easyadmin', array(
            'action' => 'list',
            'entity' => $this->request->query->get('entity'),
        ));
    }


    /**
     * Merge a single project's update branch.
     *
     * @return RedirectResponse
     */
    public function merge_UpdateAction()
    {
        $id = $this->request->query->get('id');

        $this->messageBus->dispatch(new MergeUpdateProject((int)$id));

        $project = $this->em->getRepository(Project::class)->find($id);
        $this->addFlash('notice', sprintf('Merge queued for project %s (%s)', $project->getTitle(), $project->getProjectId()));

        // Redirect to the 'list' view of the given entity.
        return $this->redirectToRoute('easyadmin', array(
            'action' => 'list',
            'entity' => $this->request->query->get('entity'),
        ));
    }
}
