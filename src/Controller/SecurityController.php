<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ResetPasswordRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints\DateTime as ConstraintsDateTime;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SecurityController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(Request $request, 
                                EntityManagerInterface $emi, 
                                    UserPasswordHasherInterface $hashpassword,
                                        MailerInterface $mailer
                                    ): Response
    {
        $user = new User();
        $userForm = $this->createForm(UserType::class, $user);
        $userForm->handleRequest($request);
        if ($userForm->isSubmitted() && $userForm->isValid()){
            $avatar = $userForm->get('avatarFile')->getData();
            // $folder = $this->getParameter('profile.folder');
            $extension = $avatar->guessExtension();
            $filename = bin2hex(random_bytes(10)) . '.' . $extension;
            $avatar->move("../public/profiles", $filename);
            $user->setAvatar('profiles/' . $filename);
            $user->setPassword($hashpassword->hashPassword($user, $user->getPassword()));
            $emi->persist($user);
            $emi->flush();
            $this->addFlash('success', 'Welcome to Excelsior !');
            $email = new TemplatedEmail();
            $email->to($user->getEmail())
                  ->subject('Bienvenue sur Excelsior')
                  ->htmlTemplate('@email_templates/welcome.html.twig')
                  ->context([
                    'username' => $user->getFirstname()
                  ]);
            $mailer->send($email);        
            return $this->redirectToRoute('login');
        }

        return $this->render('security/register.html.twig', [
            'form' => $userForm->createView(),
        ]);
    }

    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $username = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'error' => $error,
            'username' => $username
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout()
    {
        return $this->render('base.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'reset-password')]
    public function resetPassword(Request $request, $token, EntityManagerInterface $emi, ResetPasswordRepository $rprepo, UserPasswordHasherInterface $hashpassword,)
    {
        // dump($token);
        $passReset = $rprepo->findOneBy(['token' => $token]);
        if (!$passReset || $passReset->getExpiredDate() > new ConstraintsDateTime('now')) {
            if ($passReset) {
                $emi->remove($passReset);
                $emi->flush();
            }
            $this->addFlash('error', 'Le délai est expiré, veuillez refaire une demande');
            return $this->redirectToRoute('login');
        }

        $passwordForm = $this->createFormBuilder()
            ->add('password', PasswordType::class, [
                'label' => "Votre nouveau mot de passe",
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit comporter au moins 6 caractères'
                    ]),
                    new NotBlank([
                        'message' => 'Veuillez renseigner ce champ'
                    ])
                ]
            ])
            ->getForm();

            $passwordForm->handleRequest($request);
            if ($passwordForm->isSubmitted() && $passwordForm->isValid()){
                $password = $passwordForm->get('password')->getData();
                $user = $passReset->getUser();
                $hash = $hashpassword->hashPassword($user, $password);
                $user->setPassword($hash);
                $emi->remove($passReset);
                $emi->flush();
                $this->addFlash('success', 'Mot de passe modifié !');
                return $this->redirectToRoute('login');
            }


        return $this->render('security/valid-reset-password.html.twig', [
            'form' => $passwordForm->createView()
        ]);
    }

    #[Route('/reset', name: 'reset')]
    public function reset(Request $request,
                             UserRepository $urepo,
                              ResetPasswordRepository $rprepo,
                               EntityManagerInterface $emi,
                                MailerInterface $mailer,
                                  RateLimiterFactory $passwordRecoveryLimiter)
    {
        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());

        if ($limiter->consume(1)->isAccepted() === false) {
            $this->addFlash('error', 'Trop d\'essais, veuillez patienter une demi heure !');
            return $this->redirectToRoute('login');
        }

        $emailForm = $this->createFormBuilder()->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez renseigner votre email'
                ])
            ]
        ])->getForm();

        $emailForm->handleRequest($request);
        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $email = $emailForm->get('email')->getData(); // on récupère le mail saisi dans le formulaire
            $user = $urepo->findOneBy(['email' => $email]); // on vérifie si le mail existe en BDD
            if ($user) {
                $oldPass = $rprepo->findOneBy(['user' => $user]); // vérification qu'il n'y ait pas un token pour cet utilisateur
                if ($oldPass) {
                    $emi->remove($oldPass); // si oui on le supprime
                    $emi->flush();
                }
                $resetPassword = new ResetPassword();
                $resetPassword->setUser($user); // on lui indique l'id en lui passant l'objet (l'entité)
                $resetPassword->setExpiredDate(new DateTimeImmutable('+1 hour')); // on donne une validité d'une heure au lien envoyé par mail
                // création du token
                $token = substr(str_replace( ['+' , '/' , '='], '', base64_encode(random_bytes(30))), 0, 20);
                $resetPassword->setToken($token);
                $emi->persist($resetPassword);
                $emi->flush();
                $newMail = new TemplatedEmail();
                $newMail->to($email)
                        ->subject('Demande de réinitialisation de mot de passe')
                        ->htmlTemplate('@email_templates/reset-password-email.html.twig')
                        ->context([
                            'token' => $token
                        ]);
                $mailer->send($newMail);
            }
            $this->addFlash('success', "Un email pour réinitialiser votre mot de passe vous a été envoyé");
            return $this->redirectToRoute('home');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $emailForm->createView()
        ]);
    }
}
