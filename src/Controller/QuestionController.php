<?php

namespace App\Controller;

use App\Form\QuestionType;
use App\Form\AnswerType;
use App\Entity\Ask;
use App\Entity\Answer;
use App\Entity\Vote;
use App\Repository\AskRepository;
use App\Repository\UserRepository;
use App\Repository\VoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Mailer\MailerInterface;

class QuestionController extends AbstractController
{
    #[Route('/question/ask', name: 'form_question')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request, EntityManagerInterface $emi): Response
    {

        $user = $this->getUser();

        $question = new Ask();

        $formQuestion = $this->createForm(QuestionType::class, $question);

        $formQuestion->handleRequest($request);

        if ($formQuestion->isSubmitted() && $formQuestion->isValid()) {
            $question->setNumberResponse(0);
            $question->setNote(0);
            $question->setDate(new \DateTimeImmutable());
            $question->setAuthor($user);
            // dd($question);
            $emi->persist($question);
            $emi->flush();
            $this->addFlash('success', 'Question ajoutée'); // cat list
            return $this->redirectToRoute('home');
        }

        return $this->render('question/index.html.twig', [
            'form' => $formQuestion->createView(),
        ]);
    }
 
    #[Route('/question/{id}', name: 'one_question')]
    public function show(Ask $ask, Request $request, EntityManagerInterface $emi, MailerInterface $mailer, AskRepository $arepo)
    {

        $params = [
            'question' => $ask,
        ];

        $user = $this->getUser();

        if ($user) {
            $answer = new Answer();
            $answerForm = $this->createForm(AnswerType::class, $answer);
            $answerForm->handleRequest($request);
    
            if ($answerForm->isSubmitted() && $answerForm->isValid()) {

                // $email = new TemplatedEmail(); 
                // $email->from($user->getEmail())
                //       ->to($mail)
                //       ->subject('Nouvelle réponse à une question suivie')
                //       ->htmlTemplate('@email_templates/new-answer.html.twig')
                //       ->context([
                //         'user' => $user,
                //         'friend' => $friend
                //       ]);
                // $mailer->send($email);

                $answer->setDate(new \DateTimeImmutable());
                $answer->setNote(0);
                $answer->setQuestion($ask);
                $answer->setAuthor($user);
                $ask->setNumberResponse($ask->getNumberResponse() + 1);
                $emi->persist($answer);
                $emi->flush();

                $this->addFlash('success', 'Réponse bien prise en compte');
                return $this->redirect($request->getUri());
            }
            $params['form'] = $answerForm->createView();
        }

        return $this->render('question/onequestion.html.twig', $params);
    }

    #[Route('/ask/{id}/{total}', name: 'question_note')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function ratingAsk(Ask $ask, Request $request, EntityManagerInterface $emi, int $total, VoteRepository $vrepo)
    {

        $user = $this->getUser();  // on récupère l'utilisateur
        if ($user !== $ask->getAuthor()){  // vérification que la question ou la réponse n'est pas un de cet utlisateur
             $vote = $vrepo->findBy([ // récupérer le vote qui concerne la bonne question et le bon utilisateur
                'author' => $user,
                'question' => $ask
             ])[0] ?? null;
             if ($vote) {
                if (($vote->isIsLiked() && $total > 0) || (!$vote->isIsLiked() && $total < 0) ) {
                    $emi->remove($vote);
                    $ask->setNote($ask->getNote() + ($total > 0 ? -1 : 1));
                } else {
                    $vote->setIsLiked(!$vote->isIsLiked());
                    $ask->setNote($ask->getNote() + ($total > 0 ? 2 : -2));
                }
            } else { // s'il n'y a pas eu de votes
                $vote = new Vote();
                $vote->setAuthor($user);
                $vote->setQuestion($ask);
                $vote->setIsLiked($total > 0 ? true : false);
                $ask->setNote($ask->getNote() + $total);
                $emi->persist($vote);
            }
            $emi->flush();
        }

        // $ask->setNote($ask->getNote() + $total);
        $emi->flush();
        $referer = $request->server->get('HTTP_REFERER');

        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }

    #[Route('/answer/{id}/{total}', name: 'reponse_note')]
    #[IsGranted('ROLE_USER')]
    public function ratingAnswer(Answer $answer, VoteRepository $vrepo, Request $request, EntityManagerInterface $emi, int $total)
    {

        $user = $this->getUser();
        if ($user !== $answer->getAuthor()) {
            $vote = $vrepo->findBy([
                'author' => $user,
                'reponses' => $answer
            ])[0] ?? null;
            if ($vote) {
                if (($vote->isIsLiked() && $total > 0) || (!$vote->isIsLiked() && $total < 0)) {
                    $emi->remove($vote);
                    $answer->setNote($answer->getNote() + ($total > 0 ? -1 : 1));
                } else {
                    $vote->setIsLiked(!$vote->isIsLiked());
                    $answer->setNote($answer->getNote() + ($total > 0 ? 2 : -2));
                }
            } else {
                $vote = new Vote();
                $vote->setAuthor($user);
                $vote->setReponses($answer);
                $vote->setIsLiked($total > 0 ? true : false);
                $answer->setNote($answer->getNote() + $total);
                $emi->persist($vote);
            }

            // $ask->setNote($ask->getNote() + $total);
            $emi->flush();
        }
        $referer = $request->server->get('HTTP_REFERER');

        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }
}
