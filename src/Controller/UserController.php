<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Services\UploaderPicture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


class UserController extends AbstractController
{
    // Afficher le profil User
    // Mettre à jour le profil User (changer mdp, changer d'image de profil)

    // Page du profil de l'utilisateur connecté
    #[Route('/user_profile', name: 'current_user')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function currentUserProfile(Request $request, UploaderPicture $uploaderPicture, UserPasswordHasherInterface $pwHash, EntityManagerInterface $em, User $user): Response
    {
        /**
         * @var User $user
         */

        $user = $this->getUser();
        $userForm = $this->createForm(UserType::class, $user, ['new_user' => false]); // Plus besoinde récupérer le user depuis le formBuilder
        $userForm->remove('password');
        $userForm->add('newPassword', PasswordType::class, [
            'label' => 'Nouveau mot de passe',
            'required' => false
        ]);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $newPassword = $user->getNewPassword();
            if ($newPassword) {
                $hash = $pwHash->hashPassword($user, $newPassword);
                $user->setPassword($hash);
            }

            $avatar = $userForm->get('pictureFile')->getData();
            if ($avatar) {
                $user->setAvatar($uploaderPicture->upload($avatar, $user->getAvatar()));
            }

            $em->flush();
            //dump($user);
            $this->addFlash("success", "Modifications sauvegardées avec succès.");
        }

        return $this->render('user/current_user.html.twig', [
            'form' => $userForm->createView()
        ]);
    }

    #[Route('/user/questions/', name: 'show_questions')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function showQuestions(): Response
    {
        return $this->render('user/show_questions.html.twig');
    }

    #[Route('/user/comments', name: 'show_comments')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function showComments(): Response
    {
        return $this->render('user/show_comments.html.twig');
    }

    #[Route('/user/{id}', name: 'user')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function userProfile(User $user): Response
    {
        $currentUser = $this->getUser();

        if ($currentUser === $user) {
            return $this->redirectToRoute('current_user');
        }

        return $this->render('user/show.html.twig', [
            'user' => $user
        ]);
    }
}
