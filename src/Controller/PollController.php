<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Poll;
use App\Entity\PollOption;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/events/{id}/polls')]
class PollController extends AbstractController
{
    /**
     * ✅ Créer un sondage pour un événement
     */
    #[Route('', name: 'add_poll', methods: ['POST'])]
    public function addPoll(Event $event, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? null;
        $options = $data['options'] ?? [];

        if (!$question) {
            return new JsonResponse(['message' => 'Question obligatoire'], 400);
        }

        $poll = new Poll();
        $poll->setEvent($event);
        $poll->setQuestion($question);

        foreach ($options as $opt) {
            $option = new PollOption();
            $option->setPoll($poll);
            $option->setText(is_array($opt) ? $opt['text'] : $opt);
            $option->setVotes(0);
            $poll->addOption($option);
            $em->persist($option);
        }

        $em->persist($poll);
        $em->flush();

        return new JsonResponse([
            'id' => $poll->getId(),
            'question' => $poll->getQuestion(),
            'options' => array_map(fn($o) => [
                'id' => $o->getId(),
                'text' => $o->getText(),
                'votes' => $o->getVotes()
            ], $poll->getOptions()->toArray()),
        ], 201);
    }

    /**
     * 📋 Récupérer les sondages d’un événement
     */
    #[Route('', name: 'api_event_polls', methods: ['GET'])]
    public function getPolls(Event $event): JsonResponse
    {
        $polls = $event->getPolls();
        $data = [];

        foreach ($polls as $poll) {
            $options = [];
            foreach ($poll->getOptions() as $opt) {
                $options[] = [
                    'id' => $opt->getId(),
                    'text' => $opt->getText(),
                    'votes' => $opt->getVotes(),
                ];
            }

            $data[] = [
                'id' => $poll->getId(),
                'question' => $poll->getQuestion(),
                'options' => $options,
            ];
        }

        return $this->json($data);
    }

    /**
     * 🗳️ Voter pour une option
     */
    #[Route('/vote/{optionId}', name: 'vote_poll_option', methods: ['POST'])]
    public function vote(int $optionId, EntityManagerInterface $em): JsonResponse
    {
        $option = $em->getRepository(PollOption::class)->find($optionId);

        if (!$option) {
            return new JsonResponse(['message' => 'Option introuvable'], 404);
        }

        $option->setVotes($option->getVotes() + 1);
        $em->flush();

        return new JsonResponse([
            'message' => 'Vote enregistré avec succès',
            'option' => [
                'id' => $option->getId(),
                'text' => $option->getText(),
                'votes' => $option->getVotes(),
            ]
        ]);
    }

    /**
     * ❌ Supprimer un sondage
     */
    #[Route('/{poll}', name: 'delete_poll', methods: ['DELETE'])]
    public function deletePoll(Poll $poll, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($poll);
        $em->flush();

        return new JsonResponse(['message' => 'Sondage supprimé avec succès']);
    }
}
