<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert; // Pour vérifier que les conditions sont remplis avant d'exécuter une opération comme l'envoi d'un formulaire

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Veuillez renseigner ce champ.")] // Affiche ce message si le champ est vide.
    #[Assert\Length(min: 5, minMessage: "Le titre est trop court.", max: 200, maxMessage: "Le titre est trop long.")] // Demande à l'utilisateur d'écrire un titre dont la longueur est entre 5 et 200 caractères
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Veuillez détailler votre question.")] // Affiche ce message si le champ est vide.
    #[Assert\Length(min: 20, minMessage: "Veuillez détailler plus.", max: 2000, maxMessage: "Le contenu de la question est trop long.")] // Demande à l'utilisateur d'écrire un contenu dont la longueur est entre 20 et 500 caractères
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?int $rating = null;

    #[ORM\Column]
    private ?int $nbReponse = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: Vote::class)]
    private Collection $votes;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getNbReponse(): ?int
    {
        return $this->nbReponse;
    }

    public function setNbReponse(int $nbReponse): static
    {
        $this->nbReponse = $nbReponse;

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setQuestion($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getQuestion() === $this) {
                $comment->setQuestion(null);
            }
        }

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    /**
     * @return Collection<int, Vote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(Vote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setQuestion($this);
        }

        return $this;
    }

    public function removeVote(Vote $vote): static
    {
        if ($this->votes->removeElement($vote)) {
            // set the owning side to null (unless already changed)
            if ($vote->getQuestion() === $this) {
                $vote->setQuestion(null);
            }
        }

        return $this;
    }
}
