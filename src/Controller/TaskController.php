<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Util\AtediHelper;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InterventionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/task')]
class TaskController extends AbstractController
{
    public $atediHelper;
    public $em;
    public function __construct(AtediHelper $AtediHelper)
    {
        $this->atediHelper = $AtediHelper;
    }

    #[Route("/", name: "task_index", methods: ["GET"])]
    public function index(TaskRepository $taskRepository): Response
    {
        return $this->render('task/index.html.twig', [
            'tasks' => $taskRepository->findAll(),
        ]);
    }

    #[Route("/new", name: "task_new", methods: ["GET","POST"])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($task);
            $em->flush();

            if ( $request->query->has('s') == 'intervention') {
                return $this->redirectToRoute('intervention_new');
            }

            return $this->redirectToRoute('task_show', [
                'id' => $task->getId(),
            ]);
        }

        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "task_show", methods: ["GET"])]
    public function show(Task $task, InterventionRepository $interventionRepository): Response
    {
        $interventions = $interventionRepository->findAllByTask($task->getId());

        return $this->render('task/show.html.twig', [
            'task' => $task,
            'interventions' => $interventions,
        ]);
    }

    #[Route("/{id}/edit", name: "task_edit", methods: ["GET","POST"])]
    public function edit(Request $request, Task $task, EntityManagerInterface $em, InterventionRepository $ir): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->em = $em;

            $interventionsCollection = $ir->findAllByTask($task->getId());
            foreach ( $interventionsCollection as $intervention ) {
                if ( $intervention->getStatus() != 'Terminée' ) {
                    $totalPrice = $this->atediHelper->strTotalPrice($intervention);
                    $intervention->setTotalPrice($totalPrice);
                    $this->em->persist($intervention);
                }
            }

            $this->em->flush();

            return $this->redirectToRoute('task_show', [
                'id' => $task->getId(),
            ]);
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "task_delete", methods: ["POST", "DELETE"])]
    public function delete(Request $request, Task $task, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
            $em->remove($task);
            $em->flush();
        }

        return $this->redirectToRoute('task_index');
    }
}
