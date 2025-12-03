<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Poll
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $question;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'polls', cascade: ['persist'])]
    private ?Event $event = null;

    #[ORM\OneToMany(mappedBy: 'poll', targetEntity: PollOption::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $options;

    
    public function __construct()
    {
        $this->options = new ArrayCollection();
    }
    
    public function getId(): ?int
   {
       return $this->id;
   }
    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = $question;
        return $this;
    }

    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(PollOption $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setPoll($this);
        }
        return $this;
    }
     public function removeOption(PollOption $option): self
    {
        if ($this->options->removeElement($option)) {
            if ($option->getPoll() === $this) {
                $option->setPoll(null);
            }
        }
        return $this;
    }
     public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): self
    {
        $this->event = $event;
        return $this;
    }
}
