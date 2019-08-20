<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Simple Home Page Controller to redirect to the Admin.
 *
 * It's probably better to put EasyAdminBundle here (via config) or put some
 * real application code here. Since you can do either, this is the 3rd
 * "easy to remove" option.
 */
class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="default")
     */
    public function index()
    {
        return $this->redirectToRoute('easyadmin', array(
            'action' => 'list',
            'entity' => 'Project',
        ));
    }
}
