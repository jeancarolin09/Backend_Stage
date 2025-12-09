<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Entity\Notification;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use DateTimeImmutable;

#[Route('/api/conversations')]
class ConversationController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private MessageRepository $messageRepo
    ) {}

    // ✅ Récupérer toutes les conversations de l'utilisateur
    #[Route('', methods: ['GET'])]
    public function getConversations(ConversationRepository $conversationRepo,
    MessageRepository $messageRepo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['error' => 'Unauthorized'], 401);

        $conversations = $this->conversationRepo->findByParticipant($user);
        $unread = $messageRepo->getUnreadCountByConversation($user);

        $data = array_map(function(Conversation $conv) use ($user, $unread) {
            $otherParticipants = $conv->getParticipants()
                ->filter(fn(User $u) => $u->getId() !== $user->getId())
                ->getValues();

            return [
                'id' => $conv->getId(),
                'name' => $conv->getName() ?? $this->getConversationName($otherParticipants),
                'participants' => array_map(fn(User $u) => [
                    'id' => $u->getId(),
                    'name' => $u->getName(),
                    'email' => $u->getEmail(),
                    'profilePicture' => $u->getProfilePicture(),
                    'isOnline' => $u->getIsOnline(),                // ← ICI !
                    'lastActivity' => $u->getLastActivity()?->format('c'),
                ], $otherParticipants),
                'lastMessage' => $conv->getLastMessage() ? [
                    'id' => $conv->getLastMessage()->getId(),
                    'content' => $conv->getLastMessage()->getContent(),
                    'senderName' => $conv->getLastMessage()->getSender()->getName(),
                    'senderId' => $conv->getLastMessage()->getSender()->getId(),
                    'createdAt' => $conv->getLastMessage()->getCreatedAt()->format('c'),
                    'isRead' => $conv->getLastMessage()->getSender()->getId() === $user->getId() 
                                ? true 
                                : $conv->getLastMessage()->isRead(), // ← important
              ] : null,
                'createdAt' => $conv->getCreatedAt()->format('c'),
                'updatedAt' => $conv->getUpdatedAt()->format('c'),
                
                // 🔥 FIX ICI 🔥
                'unreadCount' => $unread[$conv->getId()] ?? 0,
            ];
        }, $conversations);

        return $this->json($data);
    }

    // ✅ Créer une nouvelle conversation ou en trouver une existante
    #[Route('/create-or-find', methods: ['POST'])]
    public function createOrFindConversation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $participantIds = $data['participantIds'] ?? [];
        $user = $this->getUser();

        if (empty($participantIds)) {
            return $this->json(['error' => 'No participants provided'], 400);
        }

        // Vérifier si une conversation existe déjà avec ces participants
        $existingConv = $this->conversationRepo->findByParticipantIds(
            [...$participantIds, $user->getId()]
        );

        if ($existingConv) {
            return $this->json(['id' => $existingConv->getId()]);
        }

        // Créer une nouvelle conversation
        $conversation = new Conversation();
        $conversation->addParticipant($user);

        foreach ($participantIds as $id) {
            $participant = $this->userRepo->find($id);
            if ($participant) {
                $conversation->addParticipant($participant);
            }
        }

        $this->em->persist($conversation);
        $this->em->flush();

        return $this->json(['id' => $conversation->getId(), 'created' => true], 201);
    }

    // ✅ Récupérer les messages d'une conversation
    #[Route('/{id}/messages', methods: ['GET'])]
    public function getMessages(Conversation $conversation, Request $request): JsonResponse
    {
        $user = $this->getUser();

        // Vérifier que l'utilisateur est participant
        if (!$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 50);

        $messages = $this->messageRepo->findByConversation(
            $conversation,
            $limit,
            ($page - 1) * $limit
        );

        $data = array_map(fn(Message $msg) => [
            'id' => $msg->getId(),
            'sender' => [
                'id' => $msg->getSender()->getId(),
                'name' => $msg->getSender()->getName(),
                'profilePicture' => $msg->getSender()->getProfilePicture(),
            ],
            'content' => $msg->getContent(),
            'attachment' => $msg->getAttachmentPath(),
            'createdAt' => $msg->getCreatedAt()->format('c'),
            'editedAt' => $msg->getEditedAt()?->format('c'),
            'isOwn' => $msg->getSender()->getId() === $user->getId(),
        ], $messages);

        return $this->json($data);
    }

    // ✅ Envoyer un message
    #[Route('/{id}/messages', methods: ['POST'])]
    public function sendMessage(Conversation $conversation, Request $request): JsonResponse
    {
        $user = $this->getUser();

        // Vérifier que l'utilisateur est participant
        if (!$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? '';

        if (empty($content)) {
            return $this->json(['error' => 'Message content is required'], 400);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setContent($content);

        $conversation->setUpdatedAt(new DateTimeImmutable());
        $conversation->setLastMessage($message);

        $this->em->persist($message);
        $this->em->persist($conversation);
        // 🔔 Créer une notification pour chaque participant sauf l'expéditeur
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getId() === $user->getId()) continue;

            $notif = new Notification();
            $notif->setRecipient($participant);
            $notif->setType('message_received');
            $notif->setIsRead(false);
            $notif->setRelatedTable('message');
            $notif->setRelatedId($message->getId());

            $this->em->persist($notif);
        }
        $this->em->flush();

        return $this->json([
            'id' => $message->getId(),
            'sender' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'profilePicture' => $user->getProfilePicture(),
            ],
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format('c'),
        ], 201);
    }

    // ✅ Éditer un message
    #[Route('/messages/{messageId}', methods: ['PUT'])]
    public function editMessage(Message $message, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if ($message->getSender()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? '';

        if (empty($content)) {
            return $this->json(['error' => 'Message content is required'], 400);
        }

        $message->setContent($content);
        $message->setEditedAt(new DateTimeImmutable());

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    // ✅ Supprimer un message
    #[Route('/{conversationId}/messages/{messageId}', methods: ['DELETE'])]
    public function deleteMessage(Message $message, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if ($message->getSender()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $this->em->remove($message);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    // Fonction helper
    private function getConversationName(array $participants): string
    {
        if (count($participants) === 0) {
            return 'Conversation';
        }
        if (count($participants) === 1) {
            return $participants[0]->getName();
        }
        return implode(', ', array_map(fn(User $u) => $u->getName(), array_slice($participants, 0, 2))) . '...';
    }

    #[Route('/{conversationId}/read', methods: ['PATCH'])]
public function markConversationAsRead(int $conversationId, MessageRepository $repo, EntityManagerInterface $em)
{
    $user = $this->getUser();

    $repo->markConversationAsRead($conversationId, $user);

    return $this->json(['success' => true]);
}

}