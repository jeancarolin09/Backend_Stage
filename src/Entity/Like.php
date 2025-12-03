<?php

namespace App\Entity;

use App\Repository\LikeRepository;
use Doctrine\ORM\Mapping as ORM;
use DateTimeInterface;

#[ORM\Entity(repositoryClass: LikeRepository::class)]
#[ORM\Table(name: "event_like", uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'like_unique', columns: ['event_id', 'user_id'])
])]
#[ORM\HasLifecycleCallbacks]
class Like
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    
    // --- Getters & Setters ---

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): self { $this->event = $event; return $this; }

    public function getCreatedAt(): DateTimeInterface { return $this->created_at; }

    #[ORM\PrePersist]
    public function onPrePersist(): void {
        $this->created_at = new \DateTimeImmutable();
    }
}