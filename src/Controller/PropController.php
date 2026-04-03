<?php

namespace App\Controller;

use App\Entity\Prop;
use App\Form\PropType;
use App\Repository\PropRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InterventionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/prop')]
class PropController extends AbstractController
{
    #[Route("/", name: "prop_index", methods: ["GET"])]
    public function index(PropRepository $propRepository): Response
    {
        return $this->render('prop/index.html.twig', [
            'props' => $propRepository->findAll(),
        ]);
    }

    #[Route("/new", name: "prop_new", methods: ["GET","POST"])]
    public function new(Request $request): Response
    {
        $prop = new Prop();
        $form = $this->createForm(PropType::class, $prop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($prop);
            $em->flush();

            if ($request->query->has('s') == 'intervention') {
                return $this->redirectToRoute('intervention_new');
            }

            return $this->redirectToRoute('prop_show', [
                'id' => $prop->getId(),
            ]);
        }

        return $this->render('prop/new.html.twig', [
            'prop' => $prop,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "prop_show", methods: ["GET"])]
    public function show(Prop $prop, InterventionRepository $interventionRepository): Response
    {
        $interventions = $interventionRepository->findAllByProp($prop->getId());

        return $this->render('prop/show.html.twig', [
            'prop' => $prop,
            'interventions' => $interventions,
        ]);
    }

    #[Route("/{id}/edit", name: "prop_edit", methods: ["GET","POST"])]
    public function edit(Request $request, Prop $prop): Response
    {
        $form = $this->createForm(PropType::class, $prop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('prop_show', [
                'id' => $prop->getId(),
            ]);
        }

        return $this->render('prop/edit.html.twig', [
            'prop' => $prop,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "prop_delete", methods: ["DELETE"])]
    public function delete(Request $request, Prop $prop): Response
    {
        if ($this->isCsrfTokenValid('delete' . $prop->getId(), $request->request->get('_token'))) {
            $em->remove($prop);
            $em->flush();
        }

        return $this->redirectToRoute('prop_index');
    }
}
