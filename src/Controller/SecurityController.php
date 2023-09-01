<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ResetPasswordRepository;
use App\Repository\UserRepository; // nécessaire si l'utilisateur demande à réinitialiser son mot de passe
use App\Services\UploaderPicture;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; // Bundle d'authentification du nouvel utilisateur
use Symfony\Component\RateLimiter\RateLimiterFactory; // Permet d'imposer une limite de tentatives de demandes de réinitialisation de mot de passe ou de connexion
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Validator\Constraints\Email as ConstraintsEmail; // Pour imposer l'utilisateur à saisir un email valide
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SecurityController extends AbstractController
{
    public function __construct(private $formLoginAuthenticator)
    {
    }

    #[Route('/signup', name: 'signup')]
    public function signup(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $pwHash, UploaderPicture $uploaderPicture, UserAuthenticatorInterface $userAuthenticator, MailerInterface $mailer): Response
    { // INSCRIPTION
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        } // IMPORTANT : redirection vers la page d'accueil si l'utilisateur est déjà connecté

        $newUser = new User(); // Création du nouvel utilisateur
        $userForm = $this->createForm(UserType::class, $newUser); // Création du formulaire d'inscription
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) { // Si le formualaire est valide puis envoyé
            $hash = $pwHash->hashPassword($newUser, $newUser->getPassword()); // hashage du mot de passe
            $newUser->setPassword($hash);

            // Uploading de l'avatar

            $picture = $userForm->get('pictureFile')->getData(); // Récupération des données du fichier image
            if ($picture) {
                $path = $uploaderPicture->upload($picture); // Renvoi du chemin généré de l'image avec la fonction upload d'UploaderPicture
                $newUser->setAvatar($path); // Initialisation du chemin de l'image avec setAvatar
            } else {
                $newUser->setAvatar('img/default_profile.png'); //  On lui donne un avatar par défaut
            }

            $em->persist($newUser);
            $em->flush(); // Exécution et nettoyage du formulaire

            $this->addFlash('success', 'Bienvenue sur Quora !'); // Affichage d'un message flash comme confirmation

            $email = new TemplatedEmail(); // Nouveau template d'email avec TemplatedEmail
            $email->to($newUser->getEmail()) // Vers le destinataire, ici le nouvel utilisateur
                ->subject('Bienvenue à Quora !') // Objet de l'email
                ->htmlTemplate('@email_templates/welcome.html.twig') // Quel template utiliser pour l'email?
                ->context([
                    'username' => $newUser->getFirstname() // Récupération du nom d'utilisateur à insérer dasn l'email
                ]);
            $mailer->send($email); // Envoi de l'email

            // return $this->redirectToRoute('signin'); // redirection vers la page de connexion
            return $userAuthenticator->authenticateUser($newUser, $this->formLoginAuthenticator, $request); // redirection vers la page d'accueil après que  l'inscription est réussie
        }

        return $this->render('security/signup.html.twig', ['form' => $userForm->createView()]); // Affichage du rendu
    }

    #[Route('/signin', name: 'signin')]
    public function signin(AuthenticationUtils $authUtils): Response
    { // CONNEXION
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        } // ATTENTION : si déjà connecté, retour à la page d'accueil

        $username = $authUtils->getLastUsername(); // Récupération du dernier username (email) saisi
        $err = $authUtils->getLastAuthenticationError(); // Récupération du dernier erreur généré lors de la dernière tantative de connexion

        return $this->render('security/signin.html.twig', [
            'username' => $username,
            'err' => $err
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout() // Rien à mettre, la fonction marche automatiquement
    {
    }

    #[Route('/unregister', name: 'unregister')]
    public function unregister(User $user)
    { // Page de désinscription
        return $this->render('security/unregister.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/goodbye/{id}', name: 'goodbye')]
    public function goodbye(User $user, UserRepository $userRepo, MailerInterface $mailer)
    { // Script de désinscription
        $email = new TemplatedEmail(); // Nouveau template de mail
        $email->to($user->getEmail())
            ->subject('Confirmation de votre désinscription') // objet de l'email
            ->htmlTemplate('@email_templates/unregister.html.twig') // Choix du template de l'email
            ->context([
                'username' => $user->getFirstname() // Ajout du username dans le corps du mail
            ]);
        $mailer->send($email); // envoi

        $userRepo->confirmUnregister($user->getId()); // UserRepository : requête effaçant les données de l'utilsateur souhaite se désinscrire

        $this->addFlash('success', 'Votre désinscription est confirmée. À bientôt sur Quora !');
        // Message de confirmation

        return $this->redirectToRoute('home'); // Redirection vers la page d'accueil
    }

    #[Route('/reset-password-request', name: 'reset-password-request')]
    public function resetPasswordRequest(Request $request, UserRepository $userRepo, ResetPasswordRepository $resetPasswordRepo, EntityManagerInterface $em, MailerInterface $mailer, RateLimiterFactory $passwordRecoveryLimiter)
    { // UserRepository est nécessaire pour la réinitialisation du mot de passe

        $limiter = $passwordRecoveryLimiter->create($request->getClientIp()); // Récupération de l'adresse IP du user afin de pouvoir limiter le nombre de requêtes

        $emailForm = $this->createFormBuilder() // Création du formulaire de demande dé réinitialisation
            ->add('email', EmailType::class, [ // Champ de l'email à renseigner
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez renseigner ce champ.' // Message disant que le champ ne doit pas petre vide
                    ]),
                    new ConstraintsEmail([
                        'message' => 'Veuillez entrer un email valide.' // Pour imposer l'utilisateur à saisir un email valide
                    ])
                ]
            ])
            ->getForm();

        $emailForm->handleRequest($request);
        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            if (!$limiter->consume(1)->isAccepted()) { // Décrémente (consomme) une tentative du user concerné par son adresse IP. Si le nombre de tentative meximum (ici, 4 essais) est atteint, on bloque l'utilisateur pendant une heure (précisé dans le fichier rate_limiter.yaml)

                $this->addFlash('error', 'Nombre maximum de tentatives atteint. Veuillez attendre 1 heure avant de réessayer.');
                return $this->redirectToRoute('signin');
            }

            $email = $emailForm->get('email')->getData(); // Récupération des données de l'email

            $user = $userRepo->findOneBy(['email' => $email]); // On retrouve l'email saisi dans le formulaire pour vérification

            if ($user) {
                $oldResetPW = $resetPasswordRepo->findOneBy(['user' => $user]); // On va retouver l'ancien jeton
                if ($oldResetPW) {
                    $em->remove($oldResetPW); // On écrase l'ancien jeton afin d'en obtenir un nouveau
                    $em->flush(); // Nettoyage du formaulaire en cas d'envoi
                }

                $token = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(40))), 0, 20); // Création d'un jeton qui servira dans l'URL de demande dé réinitialisation du mot de passe. On enlèvera également +, / et = afin d'assurer que l'URL fonctionne bien. On doit aussi faire en sorte que les jetons ne soient pas de même taille car il est possible qu'ils contiennent des caractères spéciales qui seront plus courts car supprimés. D'où le endom_bytes(40).

                $resetPW = new ResetPassword(); // Appel de l'entité ResetPassword

                $resetPW->setUser($user) // Mis à jour des données de l'utilisateur
                    ->setExpiredAt(new \DateTimeImmutable('+2 hours')) // Temps limité fixé à 2 heures (trop long, on le met normalement à 10-15 minutes voire moins)
                    ->setToken(sha1($token)); // Ajout du jeton dans l'URL et hashage de celui-ci avec sha1()

                //dd($resetPW);
                $em->persist($resetPW);
                $em->flush();

                // Envoi de l'email de réinitialisation
                $resetEmail = new TemplatedEmail(); // Mise en place d'un template d'email
                $resetEmail->to($email) // Destinaire (email saisi dans le formualaire)
                    ->subject('Demande de réinitialisation de mot de passe') // Objet de l'email
                    ->htmlTemplate('@email_templates/reset-password-request.html.twig') // Template de l'email à envoyer
                    ->context([
                        'username' => $user->getFirstname(), // Nom de l'utilisateur destinaire
                        'token' => $token // Personalisation de l'URL de réinitialisation avec le jeton
                    ]);
                $mailer->send($resetEmail); // Envoi de l'email

            }
            $this->addFlash('success', 'Un email de réinitialisation de mot de passe a été envoyé.');
            return $this->redirectToRoute('home'); // Redirection vers la page d'accueil
        }

        return $this->render('security/reset-password-request.html.twig', [
            'form' => $emailForm->createView()
        ]);
    }

    #[Route('/reset-password/{token}', name: 'reset-password')]
    public function resetPassword(string $token, Request $request, ResetPasswordRepository $resetPasswordRepo, EntityManagerInterface $em, UserPasswordHasherInterface $userPwHasher, RateLimiterFactory $passwordRecoveryLimiter)
    {
        $limiter = $passwordRecoveryLimiter->create($request->getClientIp()); // Récupération de l'adresse IP du user afin de pouvoir limiter le nombre de requêtes

        if (!$limiter->consume(1)->isAccepted()) { // Décrémente (consomme) une tentative du user concerné par son adresse IP. Si le nombre de tentative meximum (ici, 4 essais) est atteint, on bloque l'utilisateur pendant une heure (précisé dans le fichier rate_limiter.yaml)

            $this->addFlash('error', 'Nombre maximum de tentatives atteint. Veuillez attendre 1 heure avant de réessayer.');
            return $this->redirectToRoute('signin');
        }

        $resetPW = $resetPasswordRepo->findOneBy(['token' => sha1($token)]); // On retrouve le jeton utilisé (et hashé) pour la demande de réinitialisation du mot de passe

        // On supprime si la date a expiré
        if (!$resetPW || $resetPW->getExpiredAt() < new DateTime('now')) {
            if ($resetPW) {
                $em->remove($resetPW);
                $em->flush();
            }
            $this->addFlash('error', 'Votre demande de réinitialisation de mot de passe a expiré. Veuillez réitérer votre demande.');
            return $this->redirectToRoute('signin'); // Redirection vers la page de connexion
        }

        $pwResetForm = $this->createFormBuilder() // Création du formulaire de réinitialisation du mot de passe
            ->add('password', PasswordType::class, [ // Champ du nouveau mot de passe
                'label' => 'Nouveau mot de passe',
                'constraints' => [
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Le mot de passe doit faire au moins 8 caractères.' // Afficjage d'un message si l'utilisateur n'a pas un mot de passe faisant au moins 8 caractères
                    ]),
                    new NotBlank([
                        'message' => 'Veuillez renseigner ce champ.' // Affiche ce message si le champ est vide.
                    ])
                ]
            ])
            ->getForm();

        $pwResetForm->handleRequest($request);
        if ($pwResetForm->isSubmitted() && $pwResetForm->isValid()) {
            $newPassword = $pwResetForm->get('password')->getData(); // Récupération des données du nouveau mot de passe

            $user = $resetPW->getUser(); // Récupération de l'utilisateur qui a fait la demande

            $hashPw = $userPwHasher->hashPassword($user, $newPassword); // Hashage du nouveau mot de passe

            $user->setPassword($hashPw); // Récupération du nouveau mot de passe hashé

            $em->remove($resetPW); // On enlève le jeton une fois ke nouveau mot de passé mis en place

            $em->flush(); // Nettoyage du formulaire en cas d'envoi

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');

            return $this->redirectToRoute('signin'); // Redirection vers la page de connexion
        }

        return $this->render('security/reset_password_form.html.twig', [
            'form' => $pwResetForm->createView()
        ]);
    }
}
