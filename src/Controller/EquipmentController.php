<?php

namespace App\Controller;

use App\Entity\Equipment;
use App\Form\EquipmentType;
use App\Repository\EquipmentRepository;
use App\Repository\InterventionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/equipment')]
class EquipmentController extends AbstractController
{
    #[Route("/", name: "equipment_index", methods: ["GET"])]
    public function index(EquipmentRepository $equipmentRepository): Response
    {
        return $this->render('equipment/index.html.twig', [
            'equipments' => $equipmentRepository->findAll(),
        ]);
    }

    #[Route("/new", name: "equipment_new", methods: ["GET","POST"])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $equipment = new Equipment();
        $form = $this->createForm(EquipmentType::class, $equipment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($equipment);
            $em->flush();

            if ( $request->query->has('s') == 'intervention') {
                return $this->redirectToRoute('intervention_new');
            }

            return $this->redirectToRoute('equipment_show', [
                'id' => $equipment->getId(),
            ]);
        }

        return $this->render('equipment/new.html.twig', [
            'equipment' => $equipment,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "equipment_show", methods: ["GET"])]
    public function show(Equipment $equipment, InterventionRepository $interventionRepository): Response
    {
        $interventions = $interventionRepository->findAllByEquipment($equipment->getId());

        return $this->render('equipment/show.html.twig', [
            'equipment' => $equipment,
            'interventions' => $interventions,
        ]);
    }

    #[Route("/{id}/edit", name: "equipment_edit", methods: ["GET","POST"])]
    public function edit(Request $request, Equipment $equipment, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EquipmentType::class, $equipment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('equipment_show', [
                'id' => $equipment->getId(),
            ]);
        }

        return $this->render('equipment/edit.html.twig', [
            'equipment' => $equipment,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "equipment_delete", methods: ["POST", "DELETE"])]
    public function delete(Request $request, Equipment $equipment, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$equipment->getId(), $request->request->get('_token'))) {
            // Vérifier s'il y a des interventions liées
            if ($equipment->getInterventions()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer "' . $equipment->getTitle() . '". Cet équipement est utilisé par ' . $equipment->getInterventions()->count() . ' intervention(s). Supprimez d\'abord les interventions associées.');
            } else {
                $em->remove($equipment);
                $em->flush();
                $this->addFlash('success', 'L\'équipement a été supprimé avec succès.');
            }
        }

        return $this->redirectToRoute('equipment_index');
    }
}
