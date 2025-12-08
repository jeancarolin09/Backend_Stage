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
use App\Entity\Guest;
use App\Entity\Poll;
use App\Entity\Rsvp;
use App\Entity\Activity;
use App\Entity\Comment; 
use App\Entity\Like;  
use App\Entity\Bookmark;  

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

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image = null; // Nouvelle propriété pour la photo de l'événement

    #[ORM\Column(type: 'date')]
    private DateTimeInterface $event_date;

    #[ORM\Column(type: 'time')]
    private DateTimeInterface $event_time;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $event_location = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $updated_at;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Invitation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $invitations;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Guest::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $guests;

    #[ORM\OneToMany(mappedBy: "event", targetEntity: Poll::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $polls;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Rsvp::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rsvps;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Activity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $activities;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $is_publicly_shared = false; // Remplacer 'status' par un booléen de partage

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Comment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $comments; 

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Like::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $likes; 

    #[ORM\OneToMany(mappedBy:"event", targetEntity:Bookmark::class, cascade:["persist", "remove"], orphanRemoval:true)]
    private Collection $bookmarks;

    public function __construct()
    {
        $this->invitations = new ArrayCollection();
        $this->guests = new ArrayCollection();
        $this->polls = new ArrayCollection();
        $this->rsvps = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->bookmarks = new ArrayCollection();
   }

    // --- Invitations ---
    public function getInvitations(): Collection { return $this->invitations; }
    public function addInvitation(Invitation $invitation): self {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setEvent($this);
        }

        return $this;
    }
    public function removeInvitation(Invitation $invitation): self {
        if ($this->invitations->removeElement($invitation)) {
            if ($invitation->getEvent() === $this) $invitation->setEvent(null);
        }

        return $this;
    }

    // --- Polls ---
    public function getPolls(): Collection { return $this->polls; }
    public function addPoll(Poll $poll): self {
        if (!$this->polls->contains($poll)) {
            $this->polls->add($poll);
            $poll->setEvent($this);
        }

        return $this;
    }
    public function removePoll(Poll $poll): self {
        if ($this->polls->removeElement($poll)) {
            if ($poll->getEvent() === $this) $poll->setEvent(null);
        }
        return $this;
    }

    // --- Activities ---
    public function getActivities(): Collection { return $this->activities; }
    public function addActivity(Activity $activity): self {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setEvent($this);
        }
        return $this;
    }
    public function removeActivity(Activity $activity): self {
        if ($this->activities->removeElement($activity)) {
            if ($activity->getEvent() === $this) $activity->setEvent(null);
        }
        return $this;
    }

    // --- Getters / Setters généraux ---
    public function getId(): ?int { return $this->id; }
    public function getOrganizer(): ?User { return $this->organizer; }
    public function setOrganizer(User $user): self { $this->organizer = $user; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    
    // Nouveaux Getter et Setter pour l'image
    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getEventDate(): DateTimeInterface { return $this->event_date; }
    public function setEventDate(DateTimeInterface $event_date): self { $this->event_date = $event_date; return $this; }
    public function getEventTime(): DateTimeInterface { return $this->event_time; }
    public function setEventTime(DateTimeInterface $event_time): self { $this->event_time = $event_time; return $this; }
    public function getEventLocation(): ?string { return $this->event_location; }
    public function setEventLocation(?string $event_location): self { $this->event_location = $event_location; return $this; }
   public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getCreatedAt(): DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    public function getUpdatedAt(): DateTimeInterface { return $this->updated_at; }
    public function setUpdatedAt(DateTimeInterface $updated_at): self { $this->updated_at = $updated_at; return $this; }
    public function getComments(): Collection { return $this->comments; }
    public function addComment(Comment $comment): self {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setEvent($this);
        }
        return $this;
    }
    public function removeComment(Comment $comment): self {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getEvent() === $this) $comment->setEvent(null);
        }
        return $this;
    }
    public function getLikes(): Collection { return $this->likes; }
    public function addLike(Like $like): self {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setEvent($this);
        }
        return $this;
    }
    public function removeLike(Like $like): self {
        if ($this->likes->removeElement($like)) {
            if ($like->getEvent() === $this) $like->setEvent(null);
        }
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void {
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void {
        $this->updated_at = new \DateTimeImmutable();
    }

    public function isPubliclyShared(): bool
    {
        return $this->is_publicly_shared;
    }

    public function setIsPubliclyShared(bool $is_publicly_shared): self
    {
        $this->is_publicly_shared = $is_publicly_shared;
        return $this;
    }

    public function getBookmarks(): Collection { return $this->bookmarks; }
    public function addBookmark(Bookmark $bookmark): self {
        if (!$this->bookmarks->contains($bookmark)) {
            $this->bookmarks->add($bookmark);
            $bookmark->setEvent($this);
        }
        return $this;
    }
    public function removeBookmark(Bookmark $bookmark): self {
        if ($this->bookmarks->removeElement($bookmark)) {
            if ($bookmark->getEvent() === $this) $bookmark->setEvent(null);
        }
        return $this;
    }
}   