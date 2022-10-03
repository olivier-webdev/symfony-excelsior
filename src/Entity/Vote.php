<?php

namespace App\Entity;

use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    private ?Ask $question = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    private ?Answer $reponses = null;

    #[ORM\Column]
    private ?bool $isLiked = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getQuestion(): ?Ask
    {
        return $this->question;
    }

    public function setQuestion(?Ask $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function getReponses(): ?Answer
    {
        return $this->reponses;
    }

    public function setReponses(?Answer $reponses): self
    {
        $this->reponses = $reponses;

        return $this;
    }

    public function isIsLiked(): ?bool
    {
        return $this->isLiked;
    }

    public function setIsLiked(bool $isLiked): self
    {
        $this->isLiked = $isLiked;

        return $this;
    }
}
