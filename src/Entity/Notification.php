<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks] // Permet d'utiliser les événements Doctrine pour définir la date de création
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * L'utilisateur qui reçoit et qui doit voir cette notification.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $recipient = null;

    /**
     * Indique le type d'événement (ex: 'message_received', 'invitation_sent').
     */
    #[ORM\Column(length: 255)]
    private ?string $type = null;

    /**
     * Champ clé pour le badge: TRUE si la notification a été vue, FALSE sinon.
     */
    #[ORM\Column]
    private ?bool $isRead = false;

    /**
     * ID de l'entité liée (ex: l'ID du Message, de l'Invitation).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $relatedId = null;

    /**
     * Nom de la table source (ex: 'message', 'invitation', 'activity').
     * Utilisé pour regrouper les notifications par icône/badge dans le frontend.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $relatedTable = null;

    /**
     * Date de création de la notification.
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // --- CONSTRUCTEUR ET CALLBACKS ---

    /**
     * Définit la date de création automatiquement à la persistance.
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- GETTERS ET SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isIsRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getRelatedId(): ?int
    {
        return $this->relatedId;
    }

    public function setRelatedId(?int $relatedId): static
    {
        $this->relatedId = $relatedId;

        return $this;
    }

    public function getRelatedTable(): ?string
    {
        return $this->relatedTable;
    }

    public function setRelatedTable(?string $relatedTable): static
    {
        $this->relatedTable = $relatedTable;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Le setter est omis ou marqué comme 'protected' si vous utilisez PrePersist
    // pour que la date soit toujours définie par le système.
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
