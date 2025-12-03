<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ActivityRepository;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // L’utilisateur qui a fait l’action
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $actor = null;

    // L’utilisateur concerné par l’action (optionnel)
    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $targetUser = null;

    // Par exemple : "a créé un événement", "a voté pour le sondage", etc.
    #[ORM\Column(length: 255)]
    private ?string $action = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    // Optionnel : pour relier à un événement si nécessaire
    #[ORM\ManyToOne(targetEntity: Event::class, cascade: ['persist'], inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Event $event = null;
    /**
     * @var array Les détails supplémentaires de l'action, stockés en JSON.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private array $details = []; // <-- Nouvelle propriété pour les données structurées


    

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- GETTERS / SETTERS ---
    public function getId(): ?int { return $this->id; }

    public function getActor(): ?User { return $this->actor; }
    public function setActor(?User $actor): self { $this->actor = $actor; return $this; }

    public function getTargetUser(): ?User { return $this->targetUser; }
    public function setTargetUser(?User $targetUser): self { $this->targetUser = $targetUser; return $this; }

    public function getAction(): ?string { return $this->action; }
    public function setAction(string $action): self { $this->action = $action; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }


    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): self { $this->event = $event; return $this; }

     public function getDetails(): array
    {
        return $this->details;
    }

    public function setDetails(array $details): static
    {
        $this->details = $details;

        return $this;
    }


}
