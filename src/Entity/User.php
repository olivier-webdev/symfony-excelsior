<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Email(message: 'Email invalide')]
    #[Assert\NotBlank(message: 'Ce champ doit être rempli')]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Assert\Length(min:6, minMessage: 'Le mot de passe doit contenir au moins 6 caractères')]
    #[Assert\NotBlank(message: 'Ce champ doit être rempli')]
    private ?string $password = null;

    #[Assert\Length(min:6, minMessage: 'Le mot de passe doit contenir au moins 6 caractères')]
    private ?string $newpassword = null;

    private ?string $confirmNewpassword = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Ce champ doit être rempli')]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Ce champ doit être rempli')]
    private ?string $lastname = null;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Ask::class, orphanRemoval: true)]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Answer::class, orphanRemoval: true)]
    private Collection $reponses;

    #[ORM\Column(length: 255)]
    private ?string $avatar = null;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Vote::class, orphanRemoval: true)]
    private Collection $votes;

    #[ORM\ManyToMany(targetEntity: Ask::class, inversedBy: 'users')]
    private Collection $question_id;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->reponses = new ArrayCollection();
        $this->votes = new ArrayCollection();
        $this->question_id = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getNewPassword(): ?string
    {
        return $this->newpassword;
    }

    public function setNewPassword(string $pass): self
    {
        $this->newpassword = $pass;

        return $this;
    }

    public function getConfirmNewPassword(): ?string
    {
        return $this->confirmNewpassword;
    }

    public function setConfirmNewPassword(string $pass): self
    {
        $this->confirmNewpassword = $pass;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * @return Collection<int, Ask>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Ask $question): self
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setAuthor($this);
        }

        return $this;
    }

    public function removeQuestion(Ask $question): self
    {
        if ($this->questions->removeElement($question)) {
            // set the owning side to null (unless already changed)
            if ($question->getAuthor() === $this) {
                $question->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Answer>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(Answer $reponse): self
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses->add($reponse);
            $reponse->setAuthor($this);
        }

        return $this;
    }

    public function removeReponse(Answer $reponse): self
    {
        if ($this->reponses->removeElement($reponse)) {
            // set the owning side to null (unless already changed)
            if ($reponse->getAuthor() === $this) {
                $reponse->setAuthor(null);
            }
        }

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getFullName() : ?string {
        return $this->firstname . " " . $this->lastname;
    }

    /**
     * @return Collection<int, Vote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(Vote $vote): self
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setAuthor($this);
        }

        return $this;
    }

    public function removeVote(Vote $vote): self
    {
        if ($this->votes->removeElement($vote)) {
            // set the owning side to null (unless already changed)
            if ($vote->getAuthor() === $this) {
                $vote->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ask>
     */
    public function getQuestionId(): Collection
    {
        return $this->question_id;
    }

    public function addQuestionId(Ask $questionId): self
    {
        if (!$this->question_id->contains($questionId)) {
            $this->question_id->add($questionId);
        }

        return $this;
    }

    public function removeQuestionId(Ask $questionId): self
    {
        $this->question_id->removeElement($questionId);

        return $this;
    }
}
