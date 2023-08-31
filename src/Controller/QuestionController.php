<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Question;
use App\Entity\Vote;
use App\Form\CommentType;
use App\Form\QuestionType;
use App\Repository\QuestionRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class QuestionController extends AbstractController
{
    #[Route('/question/ask', name: 'question_ask')]
    #[IsGranted(('IS_AUTHENTICATED_REMEMBERED'))] // Protection de la route, accessible seulement si le user connecté
    public function ask(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser(); // Récupération de l'utilisateur connecté

        $newQuestion = new Question();
        $questionForm = $this->createForm(QuestionType::class, $newQuestion);
        $questionForm->handleRequest($request);

        if ($questionForm->isSubmitted() && $questionForm->isValid()) {
            $newQuestion->setRating(0)
                ->setNbReponse(0)
                ->setCreatedAt(new \DateTimeImmutable()) // \ -> appelle une fonction native de PHP
                ->setAuthor($user);
            $em->persist($newQuestion);
            $em->flush();

            $this->addFlash('success', 'Votre question a bien été ajoutée.'); // Message flash (notification)

            return $this->redirectToRoute('home');
        }

        return $this->render('question/ask.html.twig', ['form' => $questionForm->createView()]);
    }

    #[Route('/question/{id}', name: 'question_show')] // Pour afficher une question spécifique par son id
    public function show(QuestionRepository $questionRepo, Request $request, EntityManagerInterface $em, int $id) // Question est le convertisseur de paramètres, évitant de passer les repositories
    {
        $question = $questionRepo->getQuestionWithCommentAuthors($id);
        $options = ['question' => $question]; // Stockage de la variable Twig question dans un taleau, stocké dans la variable options qu'on retrouvera dans le render

        $user = $this->getUser(); // Puisqu'il n'y a d'IsGranted, on fait le getUser

        if ($user) {
            $newComment = new Comment();
            $commentForm = $this->createForm(CommentType::class, $newComment);
            $commentForm->handleRequest($request);

            if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                $newComment->setRating(0)
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setQuestion($question)
                    ->setAuthor($user); // Récupération de la question et de l'auteur

                $question->setNbReponse($question->getNbReponse() + 1); // Ajout de 1 dans le nombre de réponses

                $em->persist($newComment);
                $em->flush();

                $this->addFlash('success', 'Votre réponse a été publiée');

                return $this->redirect($request->getUri()); // Retour à la page actuelle après envoi du formulaire
            }
            $options['form'] = $commentForm->createView();
        }

        return $this->render('question/show.html.twig', $options);
    }

    // Si je suis le propriétaire de la Question, je ne peux pas voter
    // J'ai déjà liké -> je ne peux faire +1, je reclique sur like -> ça enlève le vote
    // Je n'ai pas aimé la question -> je like, ça passe de -1 à +1
    // J'ai aimé la question -> je dislike, ça passe de +1 à -1

    #[Route('question/{id}/{score}', name: 'question_rating')]
    #[IsGranted(('IS_AUTHENTICATED_REMEMBERED'))]
    public function rating(Question $question, int $score, EntityManagerInterface $em, Request $request, VoteRepository $voteRepo)
    {
        $user = $this->getUser(); // Récupération du User

        // Je m'assure que l'utilisateur n'est pas le propriétaire de la question
        if ($user != $question->getAuthor()) { // Si le User n'a pas voté sur un Comment ou une Question, on le laisse voter

            // On vérifie si l'utilisateur a déjà voté
            $vote = $voteRepo->findOneBy([
                'author' => $user,
                'question' => $question
            ]) ?? null; // On doit trouver l'user quia  liké/disliké la question, sinon il est possible qu'on ne récupère rien.

            if ($vote) { // Si le vote existe
                // Si l'user avait aimé et recliqué sur like, on retire son vote
                // Si l'user avait disliké et recliqué sur le dislike, on retire son vote
                if (($vote->getIsLiked() && $score > 0) || (!$vote->getIsLiked() && $score < 0)) {
                    $em->remove($vote);
                    $question->setRating($question->getRating() + ($score > 0 ? -1 : 1)); // Ternaire : si le score est supérieur à 0, on retire 1, sinon on ajoute 1
                } else {
                    $vote->setIsLiked(!$vote->getIsLiked());
                    $question->setRating($question->getRating() + ($score > 0 ? 2 : -2));
                }
            } else {
                $vote = new Vote(); // Création d'un objet Vote
                $vote->setAuthor($user)
                    ->setQuestion($question)
                    ->setIsLiked($score > 0 ? true : false); // Définitions de l'auteur (User connecté), la question à laquelle il a liké/disliké (score)

                $em->persist($vote);

                $question->setRating($score > 0 ? $question->getRating() + 1 : $question->getRating() - 1); //  Récupération du score actuel et ajout d'un nombre positif ou négatif (+1 ou -1) -> utilisation de la ternaire
            }
            $em->flush();
        }

        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }
}
