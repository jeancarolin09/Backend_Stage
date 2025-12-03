<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Poll;
use App\Entity\PollOption;
use App\Entity\Vote;
use App\Entity\Invitation;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/events/{id}/polls')]
class PollController extends AbstractController
{
    /**
     * Créer un sondage pour un événement
     */
    #[Route('', name: 'add_poll', methods: ['POST'])]
    public function addPoll(Event $event, Request $request, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || $event->getOrganizer() !== $user) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? null;
        $options = $data['options'] ?? [];

        if (!$question) return new JsonResponse(['message' => 'Question obligatoire'], 400);

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

         // Log Activity
       $activityLogger->log(
          'create_poll',
            $user,
            $event,
            null,
            ['poll_question' => $poll->getQuestion()]
        );

        return new JsonResponse([
            'id' => $poll->getId(),
            'question' => $poll->getQuestion(),
            'options' => array_map(fn($o) => [
                'id' => $o->getId(),
                'text' => $o->getText(),
                'votes' => $o->getVotes(),
            ], $poll->getOptions()->toArray()),
        ], 201);
    }

    /**
     * Récupérer les sondages d’un événement
     */
    #[Route('', name: 'api_event_polls', methods: ['GET'])]
    public function getPolls(Event $event, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $token = $request->headers->get('Invitation-Token');
        $invitation = $token
            ? $em->getRepository(Invitation::class)->findOneBy(['token' => $token])
            : null;

        $polls = $event->getPolls();
        $data = [];

        foreach ($polls as $poll) {
            $voteRepo = $em->getRepository(Vote::class);
            $existingVote = null;

            if ($user) {
                $existingVote = $voteRepo->findOneBy(['poll' => $poll, 'user' => $user]);
            } elseif ($invitation) {
                $existingVote = $voteRepo->findOneBy(['poll' => $poll, 'invitation' => $invitation]);
            }

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
                'userVote' => $existingVote ? $existingVote->getOption()->getId() : null,
            ];
        }

        return $this->json($data);
    }

    /**
     * Supprimer un sondage
     */
    #[Route('/{pollId}', name: 'delete_poll', methods: ['DELETE'])]
    public function deletePoll( Event $event, int $pollId, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['error' => 'Unauthorized'], 401);

        $poll = $em->getRepository(Poll::class)->find($pollId);

        if (!$poll) {
            return new JsonResponse(['message' => 'Sondage introuvable'], 404);
        }

        // Stocker la question avant la suppression
        $pollQuestion = $poll->getQuestion();
        $event = $poll->getEvent();
        $user = $this->getUser();
        if (!$user || $event->getOrganizer() !== $user) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }
        // Log activité AVANT la suppression (bonne pratique)
        $activityLogger->log(
            'delete_poll',
            $user,
            $event,
            null,
            ['poll_question' => $pollQuestion] // Garder le nom
        );

        foreach ($poll->getOptions() as $option) {
            $em->remove($option);
        }
        
        $em->remove($poll);
        $em->flush(); // Flush ici pour appliquer la suppression

        

        return new JsonResponse(['message' => 'Sondage supprimé avec succès']);
    }

    /**
 * Mettre à jour un sondage existant
 */
#[Route('/{pollId}', name: 'update_poll', methods: ['PUT'])]
public function updatePoll(Event $event, int $pollId, Request $request, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
{
    $user = $this->getUser();
    $poll = $em->getRepository(Poll::class)->find($pollId);
    if (!$poll) {
        return new JsonResponse(['message' => 'Sondage introuvable'], 404);
    }

    $data = json_decode($request->getContent(), true);
    $question = $data['question'] ?? null;
    $options = $data['options'] ?? [];

    if (!$question) {
        return new JsonResponse(['message' => 'Question obligatoire'], 400);
    }

    $poll->setQuestion($question);

    // Supprimer les anciennes options correctement
    $oldOptions = $poll->getOptions()->toArray();
    foreach ($oldOptions as $oldOption) {
        $poll->removeOption($oldOption); // utiliser la méthode de l'entité
        $em->remove($oldOption);
    }

    // Ajouter les nouvelles options
    foreach ($options as $optText) {
        $option = new PollOption();
        $option->setPoll($poll);
        $option->setText($optText);
        $option->setVotes(0);
        $em->persist($option);
    }

    $em->persist($poll);
    $em->flush();

    // Recharger le poll pour garantir que la collection est correcte
    $em->refresh($poll);

    // ✅ Log Activity CORRIGÉ : (action, actor, event, details, targetUser)
    $activityLogger->log(
        'update_poll', // 1. $action (string)
        $user,         // 2. $actor (User)
        $event,        // 3. $event (Event)
        null,
        ['poll_question' => $poll->getQuestion()] // 4. $details (array)
    );

    return new JsonResponse([
        'id' => $poll->getId(),
        'question' => $poll->getQuestion(),
        'options' => array_map(fn($o) => [
            'id' => $o->getId(),
            'text' => $o->getText(),
            'votes' => $o->getVotes(),
        ], $poll->getOptions()->toArray()),
    ]);
}



    /**
     * Voter pour une option
     */
    #[Route('/vote/{optionId}', name: 'vote_poll_option', methods: ['POST'])]
    public function vote(Event $event, int $id, int $optionId, Request $request, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
    {
        $user = $this->getUser();
        $token = $request->headers->get('Invitation-Token');
        $invitation = $token ? $em->getRepository(Invitation::class)->findOneBy(['token' => $token]) : null;

        if (!$user && !$invitation) {
            $this->logger->warning("Vote non autorisé sur l'option {$optionId} pour l'événement {$id}");
            return new JsonResponse(['message' => 'Vote non autorisé'], 403);
        }

        $pollOption = $em->getRepository(PollOption::class)->find($optionId);
        if (!$pollOption) {
            $this->logger->error("Option de sondage introuvable: ID {$optionId}");
            return new JsonResponse(['message' => 'Option introuvable'], 404);
        }

        $poll = $pollOption->getPoll();
        $voteRepo = $em->getRepository(Vote::class);

        $existingVote = null;
        if ($user && $invitation) {
            $existingVote = $voteRepo->findOneBy(['poll' => $poll, 'user' => $user]) ?? $voteRepo->findOneBy(['poll' => $poll, 'invitation' => $invitation]);
        } elseif ($user) {
            $existingVote = $voteRepo->findOneBy(['poll' => $poll, 'user' => $user]);
        } elseif ($invitation) {
            $existingVote = $voteRepo->findOneBy(['poll' => $poll, 'invitation' => $invitation]);
        }

        // Détermination de l'acteur pour le log (Utilisateur si connecté, sinon Invitation)
       $actorForLog = $user; // On utilise $user comme acteur s'il est là
        $targetUserForLog = $user; // L'utilisateur ciblé est l'acteur lui-même pour cette action.

        // Gestion du vote existant
        if ($existingVote) {
            $previousOption = $existingVote->getOption();
            if ($previousOption->getId() === $optionId) {
                $previousOption->setVotes(max(0, $previousOption->getVotes() - 1));
                $em->remove($existingVote);
                $em->flush();
         
               
               // ✅ Log Activity: Annulation CORRIGÉ
        $activityLogger->log(
            'unvote', // 1. $action (string)
            $actorForLog, // 2. $actor (User)
            $poll->getEvent(), // 3. $event (Event)
            null,
            ['poll_question' => $poll->getQuestion(), 'option_text' => $previousOption->getText()] // 4. $details (array)
            // 5. $targetUser (null ici)
        );

                 return new JsonResponse(['message' => 'Vote annulé', 'option' => ['id' => $previousOption->getId(), 'text' => $previousOption->getText(), 'votes' => $previousOption->getVotes()]]);
            } else {
                $previousOption->setVotes(max(0, $previousOption->getVotes() - 1));
                $pollOption->setVotes($pollOption->getVotes() + 1);
                $existingVote->setOption($pollOption);
                $em->persist($existingVote);
                $em->flush();

               
               // ✅ Log Activity: Modification CORRIGÉ
        $activityLogger->log(
            'vote', // 1. $action (string)
            $actorForLog, // 2. $actor (User)
            $poll->getEvent(), // 3. $event (Event)
            null,
            ['poll_question' => $poll->getQuestion(), 'option_text' => $pollOption->getText()] // 4. $details (array)
            // 5. $targetUser (null ici)
        );

                return new JsonResponse(['message' => 'Vote modifié avec succès', 'option' => ['id' => $pollOption->getId(), 'text' => $pollOption->getText(), 'votes' => $pollOption->getVotes()]]);
            }
        }

        // Nouveau vote
        $vote = new Vote();
        $vote->setPoll($poll)->setOption($pollOption);
        if ($user) $vote->setUser($user);
        if ($invitation) $vote->setInvitation($invitation);

        $pollOption->setVotes($pollOption->getVotes() + 1);
        $em->persist($vote);
        $em->flush();

         // Log Activity: Nouveau vote
        $activityLogger->log(
          'vote', // 1. $action (string)
            $actorForLog, // 2. $actor (User)
            $poll->getEvent(), // 3. $event (Event)
            null,
            ['poll_question' => $poll->getQuestion(), 'option_text' => $pollOption->getText()] // 4. $details (array)
            // 5. $targetUser (null ici)
                );
        return new JsonResponse(['message' => 'Vote enregistré avec succès', 'option' => ['id' => $pollOption->getId(), 'text' => $pollOption->getText(), 'votes' => $pollOption->getVotes()]]);
    }

                /**
         * Annuler un vote pour une option
         */
        #[Route('/unvote/{optionId}', name: 'unvote_poll_option', methods: ['POST'])]
        public function unvote(int $id, int $optionId, Request $request, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
        {
            $user = $this->getUser();
            $token = $request->headers->get('Invitation-Token');
            $invitation = $token ? $em->getRepository(Invitation::class)->findOneBy(['token' => $token]) : null;

            if (!$user && !$invitation) {
                return new JsonResponse(['message' => 'Annulation non autorisée'], 403);
            }

            $pollOption = $em->getRepository(PollOption::class)->find($optionId);
            if (!$pollOption) {
                return new JsonResponse(['message' => 'Option introuvable'], 404);
            }

            $poll = $pollOption->getPoll();
            $voteRepo = $em->getRepository(Vote::class);

            $existingVote = null;
            if ($user && $invitation) {
                $existingVote = $voteRepo->findOneBy(['poll' => $poll, 'user' => $user, 'invitation' => $invitation]);
            } elseif ($user) {
                $existingVote = $voteRepo->findOneBy(['poll' => $poll, 'user' => $user]);
            } elseif ($invitation) {
                $existingVote = $voteRepo->findOneBy(['poll' => $poll, 'invitation' => $invitation]);
            }

            if (!$existingVote) {
                return new JsonResponse(['message' => 'Aucun vote à annuler'], 400);
            }

            $previousOption = $existingVote->getOption();
            $previousOption->setVotes(max(0, $previousOption->getVotes() - 1));
            $em->remove($existingVote);
            $em->flush();

        // Log Activity
            $activityLogger->log(
                'unvote', // 1. $action (string)
                $user,   // 2. $actor (User) (On utilise $user qui est l'acteur connecté)
                $poll->getEvent(), // 3. $event (Event)
                null,
                ['poll_question' => $poll->getQuestion(), 'option_text' => $previousOption->getText()] // 4. $details (array)
                // 5. $targetUser (null ici)
            );

            return new JsonResponse([
                'message' => 'Vote annulé',
                'option' => [
                    'id' => $previousOption->getId(),
                    'text' => $previousOption->getText(),
                    'votes' => $previousOption->getVotes(),
                ],
                'invitation_id' => $invitation ? $invitation->getId() : null,
            ]);
        }


}
