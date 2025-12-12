<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InsufficientPermissionsController extends AbstractController
{
    #[Route('/insufficient-permissions', name: 'insufficient_permissions')]
    public function index(): Response
    {
        return $this->render('insufficient_permissions.html.twig', [
            'controller_name' => 'InsufficientPermissionsController',
        ]);
    }
}