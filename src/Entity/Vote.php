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

    // Le sondage concerné
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Poll $poll = null;

    // L'option choisie
    #[ORM\ManyToOne(targetEntity: PollOption::class, inversedBy: 'votesList')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PollOption $option = null;

    // L'utilisateur qui a voté (optionnel si c'est une invitation)
    #[ORM\ManyToOne]
    private ?User $user = null;

    // L'invitation qui a voté (optionnel si c'est un utilisateur connecté)
    #[ORM\ManyToOne(targetEntity: Invitation::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Invitation $invitation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(?Poll $poll): static
    {
        $this->poll = $poll;
        return $this;
    }

    public function getOption(): ?PollOption
    {
        return $this->option;
    }

    public function setOption(?PollOption $option): static
    {
        $this->option = $option;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getInvitation(): ?Invitation
    {
        return $this->invitation;
    }

    public function setInvitation(?Invitation $invitation): static
    {
        $this->invitation = $invitation;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
