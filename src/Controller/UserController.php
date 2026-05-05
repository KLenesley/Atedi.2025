<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
class UserController extends AbstractController
{
    #[Route("/", name: "user_index", methods: ["GET"])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route("/register", name: "user_register")]
    public function register(Request $request, UserPasswordHasherInterface $passwordEncoder, EntityManagerInterface $em): Response
    {
        // Récupération de l'utilisateur actuellement authentifié
        $currentUser = $this->getUser();

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encodez le mot de passe avant de le définir
            $encodedPassword = $passwordEncoder->hashPassword($user, $form->get('plainPassword')->getData());
            $user->setPassword($encodedPassword);

            // Persistez l'utilisateur dans la base de données
            $em->persist($user);
            $em->flush();

            // Reconnecter l'utilisateur initialement authentifié
            // $this->get('security.token_storage')->setToken(null);

            // Redirigez ici après la création du compte
            // (par exemple, vers une page de confirmation)
            $this->addFlash('success', 'Compte créé avec succès.');

            // // Rétablir l'utilisateur connecté précédemment
            // if ($currentUser instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            //     $token = new UsernamePasswordToken($currentUser, null, 'main', $currentUser->getRoles());
            //     $this->get('security.token_storage')->setToken($token);
            //     $this->get('session')->set('_security_main', serialize($token));
            // }

            return $this->redirectToRoute('user_index'); // Redirection vers la page d'accueil ou autre
        }

        return $this->render('user/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route("/send-reset-password/{id}", name: "admin_user_send_reset_password", methods: ["POST"])]
    #[IsGranted('ROLE_ADMIN')]
    public function sendResetPassword(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response {
        if (!$this->isCsrfTokenValid('send-reset-password' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('user_index');
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $em->flush();

        try {
            $email = (new TemplatedEmail())
                ->to($user->getEmail())
                ->from('lenesley.kylian@ik.me')
                ->subject('Réinitialisation de votre mot de passe ATEDI')
                ->htmlTemplate('user/password_reset.html.twig')
                ->context([
                    'user' => $user,
                    'resetUrl' => $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
                ]);

            $mailer->send($email);
            $this->addFlash('success', "Un mail de réinitialisation a été envoyé à " . $user->getEmail() . ".");
        } catch (\Exception $e) {
            $logger->error('Erreur envoi mail réinitialisation MDP : ' . $e->getMessage());
            $this->addFlash('error', "Impossible d'envoyer le mail de réinitialisation. Veuillez réessayer.");
        }

        return $this->redirectToRoute('user_index');
    }

    #[Route("/delete/{id}", name: "user_delete", methods: ["POST", "DELETE"])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $userId = $user->getId();
        $userName = $user->getFirstName();

        if ($this->isCsrfTokenValid('delete' . $userId, $request->request->get('_token'))) {
            // Vérifier s'il y a des interventions liées
            if ($user->getIntervention()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer l\'utilisateur "' . $user->getEmail() . '". Cet utilisateur est assigné à ' . $user->getIntervention()->count() . ' intervention(s). Supprimez d\'abord les interventions associées.');
            } else {
                $em->remove($user);
                $em->flush();
                $this->addFlash('success', "Suppression de l'utilisateur " . $userName . " réussie.");
            }
        } else {
            $this->addFlash('error', "Échec de la suppression de l'utilisateur n°" . $userId . ".");
        }

        return $this->redirectToRoute('user_index');
    }
}
