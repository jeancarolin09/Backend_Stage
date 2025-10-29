<?php

namespace App\Entity;

use App\Repository\EventOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Event;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: EventOptionRepository::class)]
class EventOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'eventOptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Event $event = null;

     #[ORM\OneToMany(mappedBy: 'eventOption', targetEntity: Vote::class, cascade: ['persist', 'remove'])]
    private Collection $votes;

     public function __construct()
    {
        $this->votes = new ArrayCollection();
    }

    #[ORM\Column(length: 20)]
    private ?string $type = null; // 'date' ou 'place'

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
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
            $vote->setEventOption($this);
        }

        return $this;
    }

    public function removeVote(Vote $vote): self
    {
        if ($this->votes->removeElement($vote)) {
            if ($vote->getEventOption() === $this) {
                $vote->setEventOption(null);
            }
        }

        return $this;
    }
}
