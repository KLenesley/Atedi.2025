<?php

namespace App\Controller;

use App\Entity\Technician;
use App\Form\TechnicianType;
use App\Repository\TechnicianRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\InterventionReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/technician')]
class TechnicianController extends AbstractController
{
    #[Route("/", name: "technician_index", methods: ["GET"])]
    public function index(TechnicianRepository $technicianRepository): Response
    {
        return $this->render('technician/index.html.twig', [
            'technicians' => $technicianRepository->findAll(),
        ]);
    }

    #[Route("/new", name: "technician_new", methods: ["GET","POST"])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $technician = new Technician();
        $form = $this->createForm(TechnicianType::class, $technician);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($technician);
            $em->flush();

            if ($request->query->has('s') && $request->query->get('s') == 'report') {
                return $this->redirectToRoute('intervention_report', [
                    'id' => $request->query->get('id'),
                ]);
            }

            return $this->redirectToRoute('technician_show', [
                'id' => $technician->getId(),
            ]);
        }

        return $this->render('technician/new.html.twig', [
            'technician' => $technician,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "technician_show", methods: ["GET"])]
    public function show(Technician $technician, InterventionReportRepository $interventionReportRepository): Response
    {
        $interventions = $interventionReportRepository->findAllByTechnician($technician->getId());

        return $this->render('technician/show.html.twig', [
            'technician' => $technician,
            'interventions' => $interventions,
        ]);
    }

    #[Route("/{id}/edit", name: "technician_edit", methods: ["GET","POST"])]
    public function edit(Request $request, Technician $technician, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TechnicianType::class, $technician);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('technician_show', [
                'id' => $technician->getId(),
            ]);
        }

        return $this->render('technician/edit.html.twig', [
            'technician' => $technician,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "technician_delete", methods: ["POST", "DELETE"])]
    public function delete(Request $request, Technician $technician, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$technician->getId(), $request->request->get('_token'))) {
            // Vérifier s'il est assigné à des rapports d'intervention
            if ($technician->getInterventionReports()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer le technicien "' . $technician->getLastName() . ' ' . $technician->getFirstName() . '". Ce technicien est assigné à ' . $technician->getInterventionReports()->count() . ' rapport(s) d\'intervention. Supprimez d\'abord les assignations.');
            } else {
                $em->remove($technician);
                $em->flush();
                $this->addFlash('success', 'Le technicien a été supprimé avec succès.');
            }
        }

        return $this->redirectToRoute('technician_index');
    }
}
