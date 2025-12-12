<?php

namespace App\Controller;


use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InterventionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/client')]
class ClientController extends AbstractController
{
    #[Route("/", name: "client_index", methods: ["GET"])]
    public function index(ClientRepository $clientRepository): Response
    {
        return $this->render('client/index.html.twig', [
            'clients' => $clientRepository->findAll(),
        ]);
    }

    #[Route("/new", name: "client_new", methods: ["GET", "POST"])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {


        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($client);
            $em->flush();

            if ($request->query->get('s') === 'intervention') {
                // Rediriger vers /intervention/new?client-id=5
                return $this->redirectToRoute('intervention_new', ['client-id' => $client->getId()]);
            }


            return $this->redirectToRoute('client_show', [
                'id' => $client->getId(),
            ]);
        }

        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "client_show", methods: ["GET"])]
    public function show(Client $client, InterventionRepository $interventionRepository): Response
    {
        $interventions = $interventionRepository->findAllByClient($client->getId());

        return $this->render('client/show.html.twig', [
            'client' => $client,
            'interventions' => $interventions,
        ]);
    }

    #[Route("/{id}/edit", name: "client_edit", methods: ["GET", "POST"])]
    public function edit(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('client_show', [
                'id' => $client->getId(),
            ]);
        }

        return $this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "client_delete", methods: ["POST"])]
    public function delete(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $clientId = $client->getId();

        if ($this->isCsrfTokenValid('delete' . $clientId, $request->request->get('_token'))) {
            $em->remove($client);
            $em->flush();

            $this->addFlash('success', "Suppression du client n°" . $clientId . " réussie.");
        } else {
            $this->addFlash('error', "Échec de la suppression du client n°" . $clientId . ".");
        }

        return $this->redirectToRoute('client_index');
    }
}
