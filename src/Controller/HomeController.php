<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\AskRepository;
use App\Repository\UserRepository;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(AskRepository $arepo, UserRepository $urepo): Response
    {

        $user = $this->getUser();

        if ($user) {
            $wiw = $urepo->find($user->getId());
        }

        $endpoint = [];

        if ($user) {
            foreach ($wiw->getQuestionId() as $w) {
                // dump($w->getId());
                // dump($w);
                array_push($endpoint, $w->getId());
            }
        }

        // dd($endpoint);

        $questions = $arepo->findBy([], ['date' => 'DESC']);

        return $this->render('home/index.html.twig', [
            'questions' => $questions,
            'endpoint' => $endpoint
        ]);
    }
}
