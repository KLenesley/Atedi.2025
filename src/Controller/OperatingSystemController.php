<?php

namespace App\Controller;

use App\Entity\OperatingSystem;
use App\Form\OperatingSystemType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InterventionRepository;
use App\Repository\OperatingSystemRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/operating/system')]
class OperatingSystemController extends AbstractController
{
    #[Route("/", name: "operating_system_index", methods: ["GET"])]
    public function index(OperatingSystemRepository $operatingSystemRepository): Response
    {
        return $this->render('operating_system/index.html.twig', [
            'operating_systems' => $operatingSystemRepository->findAll(),
        ]);
    }

    #[Route("/new", name: "operating_system_new", methods: ["GET","POST"])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $operatingSystem = new OperatingSystem();
        $form = $this->createForm(OperatingSystemType::class, $operatingSystem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($operatingSystem);
            $em->flush();

            if ( $request->query->has('s') == 'intervention') {
                return $this->redirectToRoute('intervention_new');
            }

            return $this->redirectToRoute('operating_system_show', [
                'id' => $operatingSystem->getId(),
            ]);
        }

        return $this->render('operating_system/new.html.twig', [
            'operating_system' => $operatingSystem,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "operating_system_show", methods: ["GET"])]
    public function show(OperatingSystem $operatingSystem, InterventionRepository $interventionRepository): Response
    {
        $interventions = $interventionRepository->findAllByOperatingSystem($operatingSystem->getId());

        return $this->render('operating_system/show.html.twig', [
            'operating_system' => $operatingSystem,
            'interventions' => $interventions,
        ]);
    }

    #[Route("/{id}/edit", name: "operating_system_edit", methods: ["GET","POST"])]
    public function edit(Request $request, OperatingSystem $operatingSystem, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(OperatingSystemType::class, $operatingSystem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('operating_system_show', [
                'id' => $operatingSystem->getId(),
            ]);
        }

        return $this->render('operating_system/edit.html.twig', [
            'operating_system' => $operatingSystem,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "operating_system_delete", methods: ["DELETE"])]
    public function delete(Request $request, OperatingSystem $operatingSystem, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$operatingSystem->getId(), $request->request->get('_token'))) {
            $em->remove($operatingSystem);
            $em->flush();
        }

        return $this->redirectToRoute('operating_system_index');
    }
}
