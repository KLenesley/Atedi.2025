<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Util\AtediHelper;
use App\Entity\BillingLine;
use App\Entity\Intervention;
use App\Util\DolibarrHelper;
use App\Form\BillingLineType;
use App\Form\InterventionType;
use App\Entity\InterventionReport;
use App\Repository\ActionRepository;
use App\Repository\ClientRepository;
use App\Repository\BookletRepository;
use App\Repository\SoftwareRepository;
use App\Repository\TechnicianRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\BillingLineRepository;
use App\Entity\SoftwareInterventionReport;
use App\Repository\InterventionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\SoftwareInterventionReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/intervention')]
class InterventionController extends AbstractController
{
    private $atediHelper;
    private $dolibarrHelper;

    public function __construct(AtediHelper $atediHelper, DolibarrHelper $dolibarrHelper)
    {
        $this->atediHelper = $atediHelper;
        $this->dolibarrHelper = $dolibarrHelper;
    }

    #[Route("/", name: "intervention_index", methods: ["GET"])]
    public function index(InterventionRepository $interventionRepository): Response
    {
        return $this->render('intervention/index.html.twig', [
            'interventions' => $interventionRepository->findAll(),
        ]);
    }

    #[Route("/new", name: "intervention_new", methods: ["GET", "POST"])]
    public function new(Request $request, ClientRepository $cr, EntityManagerInterface $em): Response
    {

        $intervention = new Intervention();
        $interventionReport = new InterventionReport();

        if ($request->query->has('client-id')) {
            $clientId = $request->query->getInt('client-id');
            // Vous pouvez utiliser getInt() pour obtenir directement un entier
        } else {
            // Définir une valeur par défaut si le paramètre client-id n'est pas présent
            $clientId = null; // ou une autre valeur par défaut selon votre logique
        }

        $form = $this->createForm(InterventionType::class, $intervention, [
            'clientId' => $clientId,
        ]);
        $form->handleRequest($request);



        if ($form->isSubmitted() && $form->isValid()) {

            $interventionReport->setStep(1);
            $em->persist($interventionReport);
            // $em->flush();

            $intervention->setInterventionReport($interventionReport);

            $totalPrice = $this->atediHelper->strTotalPrice($intervention);
            $intervention->setTotalPrice($totalPrice);
            $em->persist($intervention);

            //dump($intervention);

            $em->flush();

            return $this->redirectToRoute('intervention_show', [
                'id' => $intervention->getId(),
            ]);
        }

        return $this->render('intervention/new.html.twig', [
            'intervention' => $intervention,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "intervention_show", methods: ["GET", "POST"])]
    public function show(Request $request, Intervention $intervention, EntityManagerInterface $em, SoftwareRepository $sr, ActionRepository $ar, SoftwareInterventionReportRepository $sirr, BillingLineRepository $blr): Response
    {
        // $em = $em;
        $theStatus = $intervention->getStatus();

        if ($request->request->has('status')) {
            $newStatus = $request->request->get('status');

            if ($theStatus != $newStatus) {

                switch ($newStatus) {
                    case "En attente":
                        $intervention->getInterventionReport()->setStep(1);
                        $intervention->setStatus($newStatus);
                        $intervention->setReturnDate(null);
                        break;

                    case "En cours":
                        $intervention->getInterventionReport()->setStep(1);
                        $intervention->setStatus($newStatus);
                        if ($theStatus === "Terminée") {
                            $intervention->setReturnDate(null);
                        }
                        break;

                    case "Terminée":
                        if ($intervention->getInterventionReport()->getStep() == 9 && $intervention->getReturnDate()) {
                            $intervention->setStatus($newStatus);
                            $em->persist($intervention);
                            $em->flush();
                            // 1) Recherche/création du client dans Dolibarr
                            $dolibarrClientId = $this->dolibarrHelper->getDolibarrClientId($intervention->getClient());
                            if (isset($dolibarrClientId)) {
                                $this->addFlash('success', "L'ID du client '" . $intervention->getClient()->getLastName() . "' est : '" . $dolibarrClientId . "'");

                                // 2) Recherche/création du (ou des) service(s) dans Dolibarr
                                $dolibarrProductServiceId = null;
                                $dolibarrLignesFacture = array();;
                                foreach ($intervention->getTasks() as $task) {
                                    $dolibarrProductServiceId = $this->dolibarrHelper->getDolibarrProductServiceId($task, 'service');
                                    if (isset($dolibarrProductServiceId)) {
                                        $dolibarrLignesFacture[$dolibarrProductServiceId] = $task;
                                        $this->addFlash('success', "L'ID du service '" . $task->getTitle() . "' est : '" . $dolibarrProductServiceId . "'");
                                    } else {
                                        $this->addFlash('error', "Une erreur est intervenue, le service '" . $task->getTitle() . "' n'a pas été trouvé/créé dans Dolibarr.");
                                    }
                                }

                                // Ajout de lignes dans la facture
                                $billingLines = $blr->findAllByIntervention($intervention);
                                $i = 0;
                                foreach ($billingLines as $billingLine) {
                                    $i--;
                                    $dolibarrLignesFacture[$i] = $billingLine;
                                }

                                // 3) Création de la facture dans Dolibarr
                                if (count($dolibarrLignesFacture) > 0) {
                                    /// $this->addFlash('info', "Création de la facture dans Dolibarr...");
                                    $dolibarrFactureId = $this->dolibarrHelper->getDolibarrFactureId($intervention, $dolibarrClientId, $dolibarrLignesFacture);
                                    if (isset($dolibarrFactureId)) {
                                        $this->addFlash('success', "La facture (PROV" . $dolibarrFactureId . ") a été créée dans Dolibarr.");
                                    } else {
                                        $this->addFlash('error', "Une erreur est intervenue lors de la création de la facture dans Dolibarr.");
                                    }
                                }
                            } else {
                                $this->addFlash('error', "Une erreur est intervenue, le client '" . $intervention->getClient()->getLastName() . "' n'a pas été trouvé/créé dans Dolibarr.");
                            }

                            // @TODO à remettre
                            return $this->redirectToRoute('index');
                        }
                        break;
                }

                $em->persist($intervention);
                $em->flush();
            }
        }

        if ($request->request->has('return-date')) {
            $returnDate = $request->request->get('return-date');
            $intervention->setReturnDate(new \DateTime());

            $em->persist($intervention);
            $em->flush();
        }

        if ($request->request->has('download')) {

            $download = $request->request->get('download');

            switch ($download) {
                case "request":
                    $cleaningSoftwares = $sr->findAllByType('Nettoyage');
                    $actions = $ar->findAll();

                    // Configure Dompdf according to your needs
                    $pdfOptions = new Options();
                    $pdfOptions->set('defaultFont', 'Arial');

                    // Instantiate Dompdf with our options
                    $dompdf = new Dompdf($pdfOptions);

                    // Retrieve the HTML generated in our twig file
                    $html = $this->renderView('intervention/request_pdf.html.twig', [
                        'intervention' => $intervention,
                        'cleaningSoftwares' => $cleaningSoftwares,
                        'actions' => $actions,
                    ]);

                    // Load HTML to Dompdf
                    $dompdf->loadHtml($html);

                    // (Optional) Setup the paper size and orientation 'portrai<t' or 'portrait'
                    $dompdf->setPaper('A4', 'portrait');

                    // Render the HTML as PDF
                    $dompdf->render();

                    $pdfName = $intervention->getClient()->getLastName() . '-DEMANDE-' . time() . '.pdf';
                    // Output the generated PDF to Browser (force download)
                    $dompdf->stream($pdfName, [
                        "Attachment" => true
                    ]);
                    break;

                case "bill":
                    $interventionReportId = $intervention->getInterventionReport()->getId();
                    $softwares = $sirr->findAllByReport($interventionReportId);
                    $actions = $intervention->getInterventionReport()->getActions();
                    $booklets = $intervention->getInterventionReport()->getBooklets();
                    $technicians = $intervention->getInterventionReport()->getTechnicians();

                    // Configure Dompdf according to your needs
                    $pdfOptions = new Options();
                    $pdfOptions->set('defaultFont', 'Arial');

                    // Instantiate Dompdf with our options
                    $dompdf = new Dompdf($pdfOptions);

                    // Retrieve the HTML generated in our twig file
                    $html = $this->renderView('intervention/bill_pdf.html.twig', [
                        'intervention' => $intervention,
                        'softwares' => $softwares,
                        'actions' => $actions,
                        'booklets' => $booklets,
                        'technicians' => $technicians,
                    ]);

                    // Load HTML to Dompdf
                    $dompdf->loadHtml($html);

                    // (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
                    $dompdf->setPaper('A4', 'portrait');

                    // Render the HTML as PDF
                    $dompdf->render();

                    $pdfName = $intervention->getClient()->getLastName() . '-RAPPORT-' . time() . '.pdf';
                    // Output the generated PDF to Browser (force download)
                    $dompdf->stream($pdfName, [
                        "Attachment" => true
                    ]);
                    break;

                case "both":
                    $cleaningSoftwares = $sr->findAllByType('Nettoyage');
                    $actions = $ar->findAll();

                    // Configure Dompdf according to your needs
                    $pdfOptions = new Options();
                    $pdfOptions->set('defaultFont', 'Arial');

                    // Instantiate Dompdf with our options
                    $dompdf = new Dompdf($pdfOptions);

                    $interventionReportId = $intervention->getInterventionReport()->getId();
                    $softwares = $sirr->findAllByReport($interventionReportId);
                    $actions = $intervention->getInterventionReport()->getActions();
                    $booklets = $intervention->getInterventionReport()->getBooklets();
                    $technicians = $intervention->getInterventionReport()->getTechnicians();

                    // Configure Dompdf according to your needs
                    $pdfOptions = new Options();
                    $pdfOptions->set('defaultFont', 'Arial');

                    // Instantiate Dompdf with our options
                    $dompdf = new Dompdf($pdfOptions);

                    // Retrieve the HTML generated in our twig file
                    $html = $this->renderView('intervention/both_pdf.html.twig', [
                        'softwares' => $softwares,
                        'booklets' => $booklets,
                        'technicians' => $technicians,
                        'intervention' => $intervention,
                        'cleaningSoftwares' => $cleaningSoftwares,
                        'actions' => $actions,
                    ]);

                    // Load HTML to Dompdf
                    $dompdf->loadHtml($html);

                    // (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
                    $dompdf->setPaper('A4', 'portrait');

                    // Render the HTML as PDF
                    $dompdf->render();

                    $pdfName = $intervention->getClient()->getLastName() . '-DEMANDE_RAPPORT-' . time() . '.pdf';
                    // Output the generated PDF to Browser (force download)
                    $dompdf->stream($pdfName, [
                        "Attachment" => true
                    ]);
                    break;
            }
        }

        return $this->render('intervention/show.html.twig', [
            'intervention' => $intervention,
        ]);
    }

    #[Route("/{id}/report", name: "intervention_report", methods: ["GET", "POST"])]
    public function report(Request $request, EntityManagerInterface $em, Intervention $intervention, SoftwareRepository $sr, BookletRepository $br, ActionRepository $ar, SoftwareInterventionReportRepository $sirr, BillingLineRepository $blr, TechnicianRepository $tr): Response
    {
        // $em = $em;

        $interventionReport = $intervention->getInterventionReport();
        $step = $interventionReport->getStep();

        if ($request->query->has('step')) {

            $setup = $request->query->get('step');

            switch ($setup) {
                case "next":
                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);

                case "previous":
                    if ($step > 1) {
                        $interventionReport->setStep($step - 1);
                        $em->persist($interventionReport);
                        $em->flush();
                    }
                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);

                case "restart":
                    $interventionReport->setStep(1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
            }
        }

        $softwares = [];
        $booklets = $br->findAll();
        $actions = $ar->findAll();
        $technicians = $tr->findAll();
        $billingLine = new BillingLine();
        $billingLineForm = $this->createForm(BillingLineType::class, $billingLine);
        $billingLineForm->handleRequest($request);

        switch ($step) {
            case 1:
                $irTechnicians = $interventionReport->getTechnicians();
                foreach ($irTechnicians as $irTechnician) {
                    $interventionReport->removeTechnician($irTechnician);
                }
                $em->flush();

                if ($request->request->has('data')) {
                    $data = $request->request->all();
                    if (isset($data['technicians'])) {
                        $techniciansID = $data['technicians'];
                        for ($i = 0; $i < count($techniciansID); $i++) {
                            $technician = $tr->findOneById($techniciansID[$i]);
                            $interventionReport->addTechnician($technician);
                            $em->persist($interventionReport);
                        }
                    }

                    if (empty($techniciansID)) {
                        $this->addFlash('warning', "Il n'y a pas de technicien pour cette intervention.");
                    }

                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;

            case 2:
                $intervention->getInterventionReport()->setSeverityProblem([]);
                $irSoftwares = $sirr->findAllByReportAndAction($interventionReport->getId(), "Nettoyage");
                foreach ($irSoftwares as $ele) {
                    $em->remove($ele);
                }
                $interventionReport->setInternalAnalysis(NULL);
                $em->flush();

                $softwares = $sr->findAllByType('Nettoyage');

                if ($request->request->has('data')) {
                    $data = $request->request->all();
                    if (isset($data['cleaning-software'])) {
                        $cleaningSoftwaresID = $data['cleaning-software'];
                        for ($i = 0; $i < count($cleaningSoftwaresID); $i++) {
                            $software = $sr->findOneById($cleaningSoftwaresID[$i]);
                            $softwareOperation = new SoftwareInterventionReport();
                            $softwareOperation->setSoftware($software);
                            $softwareOperation->setInterventionReport($interventionReport);
                            $softwareOperation->setAction('Nettoyage');
                            $em->persist($softwareOperation);
                        }
                    }

                    // if ($request->request->has('cleaning-software')) {
                    //     $cleaningSoftwares = $request->request->get('cleaning-software');
                    //     foreach ( $cleaningSoftwares as $softwareId ) {
                    //         $software = $sr->findOneById($softwareId);
                    //         $softwareOperation = new SoftwareInterventionReport();
                    //         $softwareOperation->setSoftware($software);
                    //         $softwareOperation->setInterventionReport($interventionReport);
                    //         $softwareOperation->setAction('Nettoyage');
                    //         $em->persist($softwareOperation);
                    //     }
                    // }

                    if ($request->request->has('severity-problem')) {
                        $severityProblems = $request->request->get('severity-problem');
                        $interventionReport->setSeverityProblem($severityProblems);
                    }

                    if ($request->request->has('internal-analysis')) {
                        $internalAnalysis = $request->request->get('internal-analysis');
                        $interventionReport->setInternalAnalysis($internalAnalysis);
                    }

                    $severity = $request->request->get('severity');
                    $interventionReport->setSeverity($severity);
                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;

            case 3:
                $irActions = $interventionReport->getActions();
                foreach ($irActions as $irAction) {
                    $interventionReport->removeAction($irAction);
                }
                $em->flush();

                if ($request->request->has('data')) {

                    $data = $request->request->all();
                    if (isset($data['actions'])) {
                        $actionsID = $data['actions'];
                        for ($i = 0; $i < count($actionsID); $i++) {
                            $action = $ar->findOneById($actionsID[$i]);
                            $interventionReport->addAction($action);
                            $em->persist($interventionReport);
                        }
                    }

                    // if ($request->request->has('actions')) {
                    //     $actions = $request->request->get('actions');
                    //     foreach ( $actions as $action ) {
                    //         $action = $ar->findOneById($action);
                    //         $interventionReport->addAction($action);
                    //         $em->persist($interventionReport);
                    //     }
                    // }

                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;

            case 4:
                $irSoftwares = $sirr->findAllByReportAndAction($interventionReport->getId(), "Installé");
                foreach ($irSoftwares as $ele) {
                    $em->remove($ele);
                }
                $irSoftwares = $sirr->findAllByReportAndAction($interventionReport->getId(), "Mis à jour");
                foreach ($irSoftwares as $ele) {
                    $em->remove($ele);
                }
                $em->flush();

                $softwares = $sr->findAll();

                if ($request->request->has('data')) {

                    $parametersList = $request->request->all();
                    $parametersLength = count($parametersList);

                    $parametersList = array_slice($parametersList, 0, $parametersLength - 1, true);
                    $parametersList = array_slice($parametersList, 1, $parametersLength, true);
                    $parametersLength = count($parametersList);

                    $parametersKeys = array_keys($parametersList);

                    for ($i = 0; $i < $parametersLength; $i++) {
                        $parameter = explode("-", $parametersKeys[$i]);
                        $softwareId = $parameter[1];
                        $action = $parametersList[$parametersKeys[$i]];

                        $software = $sr->findOneById($softwareId);
                        $softwareOperation = new SoftwareInterventionReport();
                        $softwareOperation->setSoftware($software);
                        $softwareOperation->setInterventionReport($interventionReport);
                        $softwareOperation->setAction($action);
                        $em->persist($softwareOperation);
                    }

                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;

            case 5:
                $interventionReport->setWindowsInstall([]);
                $interventionReport->setWindowsVersion(NULL);
                $em->flush();

                if ($request->request->has('data')) {

                    if ($request->request->has('windows-install')) {
                        $windowsInstalls = $request->request->get('windows-install');
                        $interventionReport->setWindowsInstall($windowsInstalls);
                    }

                    if ($request->request->has('windows-version')) {
                        $windowsVersion = $request->request->get('windows-version');
                        $interventionReport->setWindowsVersion($windowsVersion);
                    }

                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;

            case 6:

                $interventionReport->setDiskState(NULL);
                $interventionReport->setUptime(NULL);
                $interventionReport->setBatteryDegradation(NULL);
                $em->flush();

                if ($request->request->has('data')) {
                    if ($request->request->has('disk-state')) {
                        $diskState = $request->request->get('disk-state');
                        $interventionReport->setDiskState($diskState);
                    }

                    if ($request->request->has('uptime')) {
                        $uptime = $request->request->get('uptime');
                        $interventionReport->setUptime($uptime);
                    }

                    if ($request->request->has('battery-degradation')) {
                        $batteryDegradation = $request->request->get('battery-degradation');
                        $interventionReport->setBatteryDegradation($batteryDegradation);
                    }

                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;

            case 7:
                $irBooklets = $interventionReport->getBooklets();
                foreach ($irBooklets as $irBooklet) {
                    $interventionReport->removeBooklet($irBooklet);
                }
                $em->flush();

                if ($request->request->has('data')) {

                    $data = $request->request->all();
                    if (isset($data['booklets'])) {
                        $bookletsID = $data['booklets'];
                        for ($i = 0; $i < count($bookletsID); $i++) {
                            $booklet = $br->findOneById($bookletsID[$i]);
                            $interventionReport->addBooklet($booklet);
                            $em->persist($interventionReport);
                        }
                    }

                    // if ($request->request->has('booklets')) {
                    //     $booklets = $request->request->get('booklets');
                    //     foreach ( $booklets as $booklet ) {
                    //         $booklet = $br->findOneById($booklet);
                    //         $interventionReport->addBooklet($booklet);
                    //         $em->persist($interventionReport);
                    //     }
                    // }

                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;

            case 8:
                $interventionReport->setComment(NULL);

                if ($request->request->has('data')) {

                    if ($request->request->has('comment')) {
                        $comment = $request->request->get('comment');

                        $interventionReport->setComment($comment);
                    }
                    $interventionReport->setStep($step + 1);
                    $em->persist($interventionReport);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;

            case 9:
                if ($request->request->has('delete-billing-line')) {
                    $billingLineId = $request->request->get('billing-line-id');

                    $billingLine = $blr->findOneById($billingLineId);
                    $intervention->removeBillingLine($billingLine);

                    $totalPrice = $this->atediHelper->strTotalPrice($intervention);
                    $intervention->setTotalPrice($totalPrice);

                    $em->persist($intervention);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }

                if ($billingLineForm->isSubmitted() && $billingLineForm->isValid()) {

                    // $em = $em;

                    $billingLine->setIntervention($intervention);
                    $em->persist($billingLine);
                    $em->flush();

                    $totalPrice = $this->atediHelper->strTotalPrice($intervention);
                    $intervention->setTotalPrice($totalPrice);
                    $em->persist($intervention);
                    $em->flush();

                    return $this->redirectToRoute('intervention_report', [
                        'id' => $intervention->getId(),
                    ]);
                }
                break;
        }

        return $this->render('intervention/report.html.twig', [
            'intervention' => $intervention,
            'softwares' => $softwares,
            'booklets' => $booklets,
            'actions' => $actions,
            'technicians' => $technicians,
            'form' => $billingLineForm->createView(),
        ]);
    }

    #[Route("/{id}/edit", name: "intervention_edit", methods: ["GET", "POST"])]
    public function edit(Request $request, Intervention $intervention, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(InterventionType::class, $intervention);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $totalPrice = $this->atediHelper->strTotalPrice($intervention);

            $intervention->setTotalPrice($totalPrice);
            $em->flush();

            return $this->redirectToRoute('intervention_show', [
                'id' => $intervention->getId(),
            ]);
        }

        return $this->render('intervention/edit.html.twig', [
            'intervention' => $intervention,
            'form' => $form->createView(),
        ]);
    }

    #[Route("/delete/{id}", name: "intervention_delete", methods: ["POST"])]
    public function delete(Request $request, EntityManagerInterface $em, Intervention $intervention, BillingLineRepository $blr): Response
    {
        $interventionId = $intervention->getId();

        if ($this->isCsrfTokenValid('delete' . $interventionId, $request->request->get('_token'))) {

            $billingLines = $blr->findAllByIntervention($intervention);

            foreach ($billingLines as $billingLine) {
                $this->addFlash('success', "Suppression de la ligne '" . $billingLine->getTitle() . "'.");
                $em->remove($billingLine);
            }

            $em->remove($intervention);
            $em->flush();
            $this->addFlash('success', "Suppression de l'intervention n°" . $interventionId . " réussie.");
        } else {
            $this->addFlash('error', "Échec de la suppression de l'intervention n°" . $interventionId . ".");
        }

        return $this->redirectToRoute('intervention_index');
    }

}
