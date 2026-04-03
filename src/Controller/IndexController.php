<?php

namespace App\Controller;

use App\Repository\InterventionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/')]
class IndexController extends AbstractController
{
    #[Route("/", name: "index", methods: ["GET"])]
    public function index(InterventionRepository $interventionRepository, Request $request): Response
    {
        $interventions = $interventionRepository->findAllOngoingByStatus();
        $filter = 'status';

        if ($request->query->has('filter')) {

            $filter = $request->query->get('filter');
            
            switch ($filter) {
                case 'status':
                    $interventions = $interventionRepository->findAllOngoingByStatus();
                    $filter = 'status';
                    break;
                case 'date':
                    $interventions = $interventionRepository->findAllOngoingByDate();
                    $filter = 'date';
                    break;
            }
        }

        return $this->render('index/index.html.twig', [
            'interventions' => $interventions,
            'filter' => $filter,
        ]);
    }
}