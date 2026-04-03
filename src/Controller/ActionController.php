<?php

namespace App\Controller;

use App\Entity\Action;
use App\Form\ActionType;
use App\Repository\ActionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/action')]
class ActionController extends AbstractController
{
    #[Route("/", name: "action_index", methods: ["GET"])]
    public function index(ActionRepository $actionRepository): Response
    {
        return $this->render('action/index.html.twig', [
            'actions' => $actionRepository->findAll(),
        ]);
    }

    #[Route("/new", name: "action_new", methods: ["GET","POST"])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $action = new Action();
        $form = $this->createForm(ActionType::class, $action);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($action);
            $em->flush();

            if ( $request->query->has('s') == 'report') {
                return $this->redirectToRoute('intervention_report', [
                    'id' => $request->query->get('id'),
                ]);
            }

            return $this->redirectToRoute('action_index');
        }

        return $this->render('action/new.html.twig', [
            'action' => $action,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}/edit", name: "action_edit", methods: ["GET","POST"])]
    public function edit(Request $request, Action $action, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ActionType::class, $action);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('action_index');
        }

        return $this->render('action/edit.html.twig', [
            'action' => $action,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "action_delete", methods: ["DELETE"])]
    public function delete(Request $request, Action $action, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$action->getId(), $request->request->get('_token'))) {
            $em->remove($action);
            $em->flush();
        }

        return $this->redirectToRoute('action_index');
    }
}
