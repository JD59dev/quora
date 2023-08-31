<?php

namespace App\Controller;

use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(QuestionRepository $questionRepo): Response
    {
        // Affichage des questions de la plus récente à la plus ancienne
        $questions = $questionRepo->getQuestionsWithAuthors();
        $questions = array_reverse($questions);

        return $this->render('home/index.html.twig', ['questions' => $questions]);
    }
}
