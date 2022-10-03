<?php

namespace App\Controller;

use App\Entity\Ask;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\AnswerRepository;
use App\Repository\AskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(private Filesystem $fs)
    {
        
    }


    #[Route('/user/{id}', name: 'user')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function user(User $user, AskRepository $arepo): Response
    {
        $currentUser = $this->getUser(); // on récupère l'utilisateur connecté
        if ($currentUser === $user) { // si c'est le même que celui a posé la question
            return $this->redirectToRoute('user_current'); // on redirige sur son profil
        }

        return $this->render('user/profile.other.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/user', name: 'user_current')]
    public function index(Request $request, EntityManagerInterface $emi, UserPasswordHasherInterface $hashpass, AnswerRepository $arepo): Response
    {
        $user = $this->getUser();
        // dd($user);
        $userForm = $this->createForm(UserType::class, $user);
        $userForm->remove('password');
        $userForm->add('newPassword', PasswordType::class, ['label' => 'Nouveau mot de passe', 'required' => false]);
        // 1 on ajoute un champ pour le formulaire
        $userForm->add('confirmNewPassword', PasswordType::class, ['label' => 'Confirmation du nouveau mot de passe', 'required' => false]);
        $userForm->handleRequest($request);

        $wantquestions = $arepo->findBy(['author' => $user->getId()]);
        // dd($wantquestions);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $newAvatar = $userForm->get('avatarFile')->getData();
            if ($newAvatar) {
                $this->fs->remove("../public/profiles/" . pathinfo($user->getAvatar(), PATHINFO_BASENAME));
                $extension = $newAvatar->guessExtension();
                $filename = bin2hex(random_bytes(10)) . '.' . $extension;
                $newAvatar->move("../public/profiles", $filename);
                $user->setAvatar('profiles/' . $filename);
            }

            $newPassword = $user->getNewPassword(); // on récupère le nouveau mot de passe saisi dans le formulaire
            $confirmPassword = $user->getConfirmNewPassword(); // on récpère la confirmation du nouveau mot de passe
            // on stocke dans une variable un booleen qui nous indique si le nouveau mot de passe edt le même que celui en bdd
            // donc on hashe le newpassword et on vérifie avec le hash de la bdd
            $test = password_verify($newPassword, $user->getPassword());

            // 1ere verif est ce que nouveau mot de passe est different de confirmation
            if ($newPassword != $confirmPassword ) { // si différent message flash et on reaffiche le form
                $this->addFlash('error', 'Les mots de passe ne correspondent pas');
                return $this->render('user/index.html.twig', [
                    'form' => $userForm->createView(),
                ]);
            } else if ($test == true ) { // 2eme verif si le password_verify retourne true
                $this->addFlash('error', 'Le mot de passe est le même qu\'en base de données');
                return $this->render('user/index.html.twig', [
                    'form' => $userForm->createView(),
                ]);
            }
            else if ($newPassword) { // si on en a saisi un
                $hash = $hashpass->hashPassword($user, $newPassword); // on hash ce nouveau mot de passe
                $user->setPassword($hash); // et on l'attribue à password dans l'entité User cela entrainera sa modification en BDD
            }
            // dump($user);
            $emi->flush();
            $this->addFlash('success', 'Profil modifié');
            // return $this->redirectToRoute('logout');

        }

        return $this->render('user/index.html.twig', [
            'form' => $userForm->createView(),
            'questions' => $wantquestions
        ]);
    }


    // 2eme partie créer la route avec le parametre attendu
    #[Route('/friend/{mail}', name: 'friend')]
    public function friend(string $mail, MailerInterface $mailer, UserRepository $urepo): Response
    {
        $user = $this->getUser(); // je récupère l'utilisateur connecté
        $friend = $urepo->findOneBy(['email' => $mail]); // on cherche en bdd l'user qui a ce mail (celui que l'on veut en ami)
        $email = new TemplatedEmail(); // on va utliser une classe TemplatedEmail pour envoyer un html en mail
        $email->from($user->getEmail()) // On récupère le mail de l'emetteur (nous en fait)
              ->to($mail) // le destinaire sera le mail récupéré
              ->subject('Demande d\'ami') // on met un sujet 
              ->htmlTemplate('user/friend.html.twig') // on précise quelle page html twig on va utiliser (on la crée auparavant)
              ->context([
                'user' => $user, // c'est le'émetteur
                'friend' => $friend // c'est notre destinataire
              ]);
        $mailer->send($email); // on envoie le mail
        $this->addFlash('success', 'Une demande d\'ami vous a été envoyé'); // un message de confirmation de demande d'accès
        return $this->redirectToRoute('home'); // on redirige sur la page d'accueil
    }

    #[Route('/accept-friend/{mail}', name: 'acceptfriend')]
    public function acceptFriend(string $mail): Response
    {

        return $this->render('user/accept-friend.html.twig', [
            'mail' => $mail
        ]);
    }

    #[Route('/follow/{id}', name: 'follow')]
    public function follow($id, Ask $ask, EntityManagerInterface $emi, AskRepository $arepo,  UserRepository $urepo)
    {   
        $user = $this->getUser(); // on récupère l'utilisateur connecté
        $askEntity = $arepo->findOneBy(['id' => $id]); // on récupère la question grace à l'id passé dans l'URL

        // dd(count($ask->getUsers()));


        if (count($ask->getUsers()) !== 0) {
            for ($i=0; $i < count($ask->getUsers()) ; $i++) {  // on boucle sur ces utilisateurs
                if (($ask->getUsers()[$i]->getId()) == $user->getId()){ 
                    $ask->removeUser($user);
                    $user->removeQuestionId($askEntity);
                    $emi->flush();
                    $endpoint = [];

                    if ($user) {
                        $wiw = $urepo->find($user->getId());
                        foreach ($wiw->getQuestionId() as $w) {
                            // dump($w->getId());
                            // dump($w);
                            array_push($endpoint, $w->getId());
                        }
                    }
                    $questions = $arepo->findBy([], ['date' => 'DESC']);
                    return $this->render('home/index.html.twig', [
                        'questions' => $questions,
                        'endpoint' => $endpoint
                    ]);
                    // exit();
                } 
                else {
                    $ask->addUser($user);
                    $emi->flush();
                    $endpoint = [];

                    if ($user) {
                        $wiw = $urepo->find($user->getId());
                        foreach ($wiw->getQuestionId() as $w) {
                            // dump($w->getId());
                            // dump($w);
                            array_push($endpoint, $w->getId());
                        }
                    }
                    $questions = $arepo->findBy([], ['date' => 'DESC']);
                    return $this->render('home/index.html.twig', [
                        'questions' => $questions,
                        'endpoint' => $endpoint
                    ]);
                }
            }
        } else {
            $ask->addUser($user);
            // $user->addQuestionId($askEntity);
            $emi->flush();
        }

        $endpoint = [];

        if ($user) {
            $wiw = $urepo->find($user->getId());
            foreach ($wiw->getQuestionId() as $w) {
                // dump($w->getId());
                // dump($w);
                array_push($endpoint, $w->getId());
            }
        }

        $questions = $arepo->findBy([], ['date' => 'DESC']);

        return $this->render('home/index.html.twig', [
            'questions' => $questions,
            'endpoint' => $endpoint
        ]);
    }


    #[Route('/join_question/{id}', name: 'join')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function join($id, AnswerRepository $arepo)
    {
        $findQuestion = $arepo->findOneBy(['id' => $id])->getQuestion()->getId();

        return $this->redirectToRoute('one_question', ['id' => $findQuestion]);
    }
}
