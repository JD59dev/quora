<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Vote;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CommentController extends AbstractController
{
    #[Route('/comment/{id}/{score}', name: 'comment_rating')]
    #[IsGranted(('IS_AUTHENTICATED_REMEMBERED'))]
    public function rating(Comment $comment, int $score, EntityManagerInterface $em, Request $request, VoteRepository $voteRepo): Response
    {
        $user = $this->getUser();

        // Je m'assure que l'utilisateur n'est pas le propriétaire de la comment
        if ($user != $comment->getAuthor()) {

            // On vérifie si l'utilisateur a déjà voté
            $vote = $voteRepo->findOneBy([
                'author' => $user,
                'comment' => $comment
            ]) ?? null;

            if ($vote) {
                // Si l'user avait aimé et recloqué sur like, on retire son vote
                // Si l'user avait disliké et recliqué sur le dislike, on retire son vote
                if (($vote->getIsLiked() && $score > 0) || (!$vote->getIsLiked() && $score < 0)) {
                    $em->remove($vote);
                    $comment->setRating($comment->getRating() + ($score > 0 ? -1 : 1)); // Ternaire : si le score est supérieur à 0, on retire 1, sinon on ajoute 1
                } else {
                    $vote->setIsLiked(!$vote->getIsLiked());
                    $comment->setRating($comment->getRating() + ($score > 0 ? 2 : -2));
                }
            } else {
                $vote = new Vote();
                $vote->setAuthor($user)
                    ->setComment($comment)
                    ->setIsLiked($score > 0 ? true : false);

                $em->persist($vote);

                $comment->setRating($score > 0 ? $comment->getRating() + 1 : $comment->getRating() - 1); //  Récupération du score actuel et ajout d'un nombre positif ou négatif (+1 ou -1) -> utilisation de la ternaire
            }
            $em->flush();
        }

        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }
}
