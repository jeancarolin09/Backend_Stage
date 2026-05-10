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
    public function getConversations(ConversationRepository $conversationRepo, MessageRepository $messageRepo): JsonResponse
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
                    'isOnline' => $u->getIsOnline(),
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
                                : $conv->getLastMessage()->isRead(),
                ] : null,
                'createdAt' => $conv->getCreatedAt()->format('c'),
                'updatedAt' => $conv->getUpdatedAt()->format('c'),
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

        // 🔥 Notifier via WebSocket la création de conversation
        $this->notifyWebSocket('conversation:created', [
            'conversationId' => $conversation->getId(),
            'participants' => [...$participantIds, $user->getId()],
        ]);

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
        $limit = $request->query->getInt('limit', 100);

        $messages = $this->messageRepo->findByConversation(
            $conversation,
            $limit,
            ($page - 1) * $limit
        );

        $data = array_map(fn(Message $msg) => [
            'id' => $msg->getId(),
            'senderId' => $msg->getSender()->getId(),
            'senderName' => $msg->getSender()->getName(),
            'sender' => [
                'id' => $msg->getSender()->getId(),
                'name' => $msg->getSender()->getName(),
                'profilePicture' => $msg->getSender()->getProfilePicture(),
            ],
            'content' => $msg->getContent(),
            'attachment' => $msg->getAttachmentPath(),
            'image' => $msg->getAttachmentPath(),
            'conversationId' => $conversation->getId(),
            'createdAt' => $msg->getCreatedAt()->format('c'),
            'editedAt' => $msg->getEditedAt()?->format('c'),
            'isOwn' => $msg->getSender()->getId() === $user->getId(),
            'isRead' => $msg->isRead(),
        ], $messages);

        return $this->json($data);
    }

    // ✅ Envoyer un message avec WebSocket
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

        // 🔥 Préparer les données du message pour WebSocket
        $messageData = [
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'senderId' => $user->getId(),
            'senderName' => $user->getName(),
            'conversationId' => $conversation->getId(),
            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            'isOwn' => false, // Pour les destinataires
            'isRead' => false,
        ];

        // 🔥 Émettre l'événement WebSocket pour diffuser le message en temps réel
        $this->notifyWebSocket('message:new', [
            'conversationId' => $conversation->getId(),
            'message' => $messageData,
        ]);

        // Retourner le message avec isOwn=true pour l'expéditeur
        return $this->json([
            'id' => $message->getId(),
            'senderId' => $user->getId(),
            'senderName' => $user->getName(),
            'sender' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'profilePicture' => $user->getProfilePicture(),
            ],
            'content' => $message->getContent(),
            'conversationId' => $conversation->getId(),
            'createdAt' => $message->getCreatedAt()->format('c'),
            'isOwn' => true,
        ], 201);
    }

    // ✅ Envoyer une image avec WebSocket
    #[Route('/{id}/messages/image', methods: ['POST'])]
    public function sendImage(Conversation $conversation, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file provided'], 400);
        }

        // Upload du fichier
        $uploadsDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/messages';
        if (!is_dir($uploadsDirectory)) {
            mkdir($uploadsDirectory, 0777, true);
        }

        $newFilename = uniqid() . '.' . $file->guessExtension();
        $file->move($uploadsDirectory, $newFilename);

        // Créer le message
        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setContent('[Image]');
        $message->setAttachmentPath('/uploads/messages/' . $newFilename);

        $conversation->setUpdatedAt(new DateTimeImmutable());
        $conversation->setLastMessage($message);

        $this->em->persist($message);
        $this->em->persist($conversation);
        $this->em->flush();

        // 🔥 Notifier via WebSocket
        $messageData = [
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'image' => $message->getAttachmentPath(),
            'senderId' => $user->getId(),
            'senderName' => $user->getName(),
            'conversationId' => $conversation->getId(),
            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            'isOwn' => false,
            'isRead' => false,
        ];

        $this->notifyWebSocket('message:new', [
            'conversationId' => $conversation->getId(),
            'message' => $messageData,
        ]);

        return $this->json([
            'id' => $message->getId(),
            'senderId' => $user->getId(),
            'image' => $message->getAttachmentPath(),
            'createdAt' => $message->getCreatedAt()->format('c'),
            'isOwn' => true,
        ], 201);
    }

    // ✅ Éditer un message avec WebSocket
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

        // 🔥 Notifier via WebSocket
        $this->notifyWebSocket('message:edited', [
            'conversationId' => $message->getConversation()->getId(),
            'messageId' => $message->getId(),
            'content' => $message->getContent(),
            'editedAt' => $message->getEditedAt()->format('c'),
        ]);

        return $this->json(['success' => true]);
    }

    // ✅ Supprimer un message avec WebSocket
    #[Route('/{conversationId}/messages/{messageId}', methods: ['DELETE'])]
    public function deleteMessage(Message $message, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if ($message->getSender()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $conversationId = $message->getConversation()->getId();
        $messageId = $message->getId();

        $this->em->remove($message);
        $this->em->flush();

        // 🔥 Notifier via WebSocket
        $this->notifyWebSocket('message:deleted', [
            'conversationId' => $conversationId,
            'messageId' => $messageId,
        ]);

        return $this->json(['success' => true]);
    }

    // ✅ Marquer la conversation comme lue avec WebSocket
    #[Route('/{conversationId}/read', methods: ['PATCH'])]
    public function markConversationAsRead(int $conversationId, MessageRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $repo->markConversationAsRead($conversationId, $user);

        // 🔥 Notifier via WebSocket que les messages ont été lus
        $this->notifyWebSocket('conversation:read', [
            'conversationId' => $conversationId,
            'userId' => $user->getId(),
        ]);

        return $this->json(['success' => true]);
    }

    // 🔥 Fonction helper pour notifier le serveur WebSocket
    private function notifyWebSocket(string $event, array $data): void
    {
        $websocketUrl = $_ENV['WEBSOCKET_SERVER_URL'] ?? 'http://localhost:3001';
        
        try {
            $ch = curl_init($websocketUrl . '/emit');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'event' => $event,
                'data' => $data
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Timeout court pour ne pas bloquer
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                error_log("WebSocket notification failed with HTTP code: $httpCode");
            }
            
            curl_close($ch);
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas bloquer l'exécution
            error_log("WebSocket notification error: " . $e->getMessage());
        }
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
}