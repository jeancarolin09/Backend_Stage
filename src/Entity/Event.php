<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;
use DateTimeInterface;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Invitation;
use App\Entity\EventOption;
use App\Entity\Poll;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: "event")]
#[ORM\HasLifecycleCallbacks]

class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $organizer = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'date')]
    private DateTimeInterface $event_date;

    #[ORM\Column(type: 'time')]
    private DateTimeInterface $event_time;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $event_location = null;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $updated_at;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Invitation::class, cascade: ['persist', 'remove'])]
    private Collection $invitations;
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventOption::class, cascade: ['persist', 'remove'])]
    private Collection $eventOptions;
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Guest::class, cascade: ['persist', 'remove'])]
    private Collection $guests;
    #[ORM\OneToMany(mappedBy: "event", targetEntity: Poll::class, cascade: ['persist', 'remove'])]
    private Collection $polls;

    public function __construct()
    {
        $this->invitations = new ArrayCollection();
        $this->eventOptions = new ArrayCollection();
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(Invitation $invitation): self
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setEvent($this);
        }

        return $this;
    }

    public function removeInvitation(Invitation $invitation): self
    {
        if ($this->invitations->removeElement($invitation)) {
            if ($invitation->getEvent() === $this) {
                $invitation->setEvent(null);
            }
        }

        return $this;
    }
     /**
     * @return Collection<int, EventOption>
     */
    public function getEventOptions(): Collection
    {
        return $this->eventOptions;
    }

    public function addEventOption(EventOption $option): self
    {
        if (!$this->eventOptions->contains($option)) {
            $this->eventOptions->add($option);
            $option->setEvent($this);
        }

        return $this;
    }

    public function removeEventOption(EventOption $option): self
    {
        if ($this->eventOptions->removeElement($option)) {
            if ($option->getEvent() === $this) {
                $option->setEvent(null);
            }
        }

        return $this;
    } 

    // --- GETTERS / SETTERS ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }

    public function setOrganizer(User $user): self
    {
        $this->organizer = $user;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getEventDate(): DateTimeInterface
    {
        return $this->event_date;
    }

    public function setEventDate(DateTimeInterface $event_date): self
    {
        $this->event_date = $event_date;
        return $this;
    }

    public function getEventTime(): DateTimeInterface
    {
        return $this->event_time;
    }

    public function setEventTime(DateTimeInterface $event_time): self
    {
        $this->event_time = $event_time;
        return $this;
    }

    public function getEventLocation(): ?string
    {
        return $this->event_location;
    }

    public function setEventLocation(?string $event_location): self
    {
        $this->event_location = $event_location;
        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTimeImmutable();
    }
    
    public function getPolls(): Collection
{
    return $this->polls;
}

public function addPoll(Poll $poll): self
{
    if (!$this->polls->contains($poll)) {
        $this->polls->add($poll);
        $poll->setEvent($this);
    }
    return $this;
}

public function removePoll(Poll $poll): self
{
    if ($this->polls->removeElement($poll)) {
        if ($poll->getEvent() === $this) {
            $poll->setEvent(null);
        }
    }
    return $this;
}
}
