<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\User;
use App\Entity\Bookmark;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/events')]
class EventController extends AbstractController
{
    private string $imageDirectory;
    private string $imagePublicPath;

    // Injection des paramètres définis dans services.yaml
    public function __construct(string $eventImagesDirectory, string $eventImagesPublicPath)
    {
        $this->imageDirectory = $eventImagesDirectory;
        $this->imagePublicPath = $eventImagesPublicPath;
    }
    
    /**
     * @param UploadedFile $imageFile
     * @return string
     */
    private function handleImageUpload(UploadedFile $imageFile): string
    {
        // Utiliser le chemin absolu injecté
        $targetDirectory = $this->imageDirectory;
        
        // Assurez-vous que le répertoire existe
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        // Simplification de la création d'un nom de fichier unique et sécurisé
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        // On rend le nom de fichier "sûr" et on ajoute un identifiant unique
        $safeFilename = str_replace(' ', '-', strtolower($originalFilename));
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        try {
            // Déplacement du fichier vers le répertoire de destination
            $imageFile->move(
                $targetDirectory,
                $newFilename
            );
            // Retourne le chemin public (URL) pour le stockage en base de données
            return $this->imagePublicPath . '/' . $newFilename; 
        } catch (\FileException $e) {
            // Loggez l'erreur
            throw new \RuntimeException('Échec de l\'upload de l\'image: ' . $e->getMessage());
        }
    }
    

     /** ------------------------------
 * 🔍 LISTER LES ÉVÉNEMENTS PUBLICS (Pour le Dashboard)
 * ------------------------------ */
#[Route('/public', name: 'api_list_events_public', methods: ['GET'])]
public function listEventsPublic(EntityManagerInterface $em): JsonResponse
{
    /** @var User $user */
    $user = $this->getUser();
    
    // Récupérer uniquement les événements qui sont partagés publiquement
    $events = $em->getRepository(Event::class)->findBy(['is_publicly_shared' => true]);

    $likeRepository = $em->getRepository(Like::class);
    $commentRepository = $em->getRepository(Comment::class);
    $bookmarkRepository = $em->getRepository(Bookmark::class);

    $data = array_map(function($e) use ($user, $likeRepository, $commentRepository, $bookmarkRepository) {
        $organizer = $e->getOrganizer();

        return [
            'id' => $e->getId(),
            'title' => $e->getTitle(),
            'image' => $e->getImage(),
            'description' => $e->getDescription(),
            // ... (inclure tous les champs nécessaires pour la carte)
            'event_date' => $e->getEventDate()->format('Y-m-d'),
            'event_time' => $e->getEventTime()->format('H:i'),
            'event_location' => $e->getEventLocation(),
            'latitude' => $e->getLatitude(),
            'longitude' => $e->getLongitude(),
            'organizer' => $organizer ? [
                'id' => $organizer->getId(),
                'email' => $organizer->getEmail(),
                'name' => $organizer->getName(),
                'profilePicture' => $organizer->getProfilePicture(),
            ] : null,
            'is_publicly_shared' => true,
            'likes_count' => $likeRepository->count(['event' => $e]),
            'comments_count' => $commentRepository->count(['event' => $e]),
            'bookmarks_count' => $bookmarkRepository->count(['event' => $e]),
            'has_liked' => $user ? (bool)$likeRepository->findOneBy(['event' => $e, 'user' => $user]) : false,
            'has_bookmarked' => $user ? (bool)$bookmarkRepository->findOneBy(['event' => $e, 'user' => $user]) : false,
        ];
    }, $events);

    return new JsonResponse($data);
   }
    /** ------------------------------
     * 📅 CRÉER UN ÉVÉNEMENT
     * ------------------------------ */
    #[Route('', name: 'api_create_event', methods: ['POST'])]
    public function createEvent(
        Request $request,
        EntityManagerInterface $em,
        ActivityLogger $activityLogger
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['error' => 'Unauthorized'], 401);

        // Les données sont lues depuis $request->request pour le form-data
        $title = $request->request->get('title');
        $event_date = $request->request->get('event_date');
        $event_time = $request->request->get('event_time');
        $imageFile = $request->files->get('image'); // Récupération du fichier

        $latitude = $request->request->get('latitude');
        $longitude = $request->request->get('longitude');

        if (!$title || !$event_date || !$event_time) {
            return new JsonResponse(['message' => 'Champs obligatoires manquants (title, event_date, event_time)'], 400);
        }

        $event = new Event();
        $event->setTitle($title);
        $event->setDescription($request->request->get('description') ?? null);
        $event->setEventDate(new \DateTime($event_date));
        $event->setEventTime(new \DateTime($event_time));
        $event->setEventLocation($request->request->get('event_location') ?? null);
        $event->setLatitude($latitude !== null ? (float)$latitude : null);
        $event->setLongitude($longitude !== null ? (float)$longitude : null);
        $event->setOrganizer($user);

        // 🖼️ GESTION DE L'UPLOAD D'IMAGE
        if ($imageFile instanceof UploadedFile) {
             try {
                 $imagePath = $this->handleImageUpload($imageFile);
                 $event->setImage($imagePath);
             } catch (\RuntimeException $e) {
                 return new JsonResponse(['error' => $e->getMessage()], 500);
             }
        }
        
        $em->persist($event);
        $em->flush();

        // 🔹 Log activité
        $activityLogger->log('create_event', $user, $event);

        return new JsonResponse([
            'message' => 'Event created successfully!',
            'event' => [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'image' => $event->getImage(),
                'event_date' => $event->getEventDate()->format('Y-m-d'),
                'event_time' => $event->getEventTime()->format('H:i'),
                'event_location' => $event->getEventLocation(),
                'latitude' => $event->getLatitude(),
                'longitude' => $event->getLongitude(),
                'organizer' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'profilePicture' => $user->getProfilePicture(),
                ],
            ],
        ]);
    }

    /** ------------------------------
     * 📖 AFFICHER UN ÉVÉNEMENT
     * ------------------------------ */
    #[Route('/{id}', name: 'get_event', methods: ['GET'])]
    public function getEvent(Event $event): JsonResponse
    {
        $organizer = $event->getOrganizer();

        return $this->json([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'image' => $event->getImage(),
            'event_date' => $event->getEventDate()->format('Y-m-d'),
            'event_time' => $event->getEventTime()->format('Y-m-d\TH:i:s'),
            'event_location' => $event->getEventLocation(),
            'latitude' => $event->getLatitude(),      // ⬅️ AJOUTÉ
            'longitude' => $event->getLongitude(),
            'organizer' => $organizer ? [
            'id' => $organizer->getId(),
            'email' => $organizer->getEmail(),
            'name' => $organizer->getName(),
            'profilePicture' => $organizer->getProfilePicture(),
            ] : null,
            'guests' => array_map(fn($inv) => [
                'id' => $inv->getId(),
                'name' => $inv->getName(),
                'email' => $inv->getEmail(),
                'status' => $inv->getStatus()
            ], $event->getInvitations()->toArray()),
            'polls' => array_map(fn($poll) => [
                'id' => $poll->getId(),
                'question' => $poll->getQuestion(),
                'options' => array_map(fn($opt) => [
                    'text' => $opt->getText(),
                    'votes' => $opt->getVotes()
                ], $poll->getOptions()->toArray()),
            ], $event->getPolls()->toArray()),
        ]);
    }

    /** ------------------------------
     * 📋 LISTER LES ÉVÉNEMENTS
     * ------------------------------ */
    #[Route('', name: 'api_list_events', methods: ['GET'])]
    public function listEvents(EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user) {
        // Renvoie une erreur si l'utilisateur n'est pas authentifié
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }
        // Récupérer tous les événements (pour la découverte, si ce n'est pas juste "mes événements")
        // Si vous voulez tous les événements :
        // $events = $em->getRepository(Event::class)->findBy(['is_publicly_shared' => true]);
        
        // Si vous voulez seulement les événements de l'organisateur (comme dans votre code original) :
         $events = $em->getRepository(Event::class)->findBy(['organizer' => $user]);

        $likeRepository = $em->getRepository(Like::class);
        $commentRepository = $em->getRepository(Comment::class);
        $bookmarkRepository = $em->getRepository(Bookmark::class);

        $data = array_map(function($e) use ($user, $likeRepository, $commentRepository, $bookmarkRepository) {
            
            // 💡 AJOUT DES STATISTIQUES POUR LE FRONT-END
            $likesCount = $likeRepository->count(['event' => $e]);
            $commentsCount = $commentRepository->count(['event' => $e]);
            $hasLiked = $user ? (bool)$likeRepository->findOneBy(['event' => $e, 'user' => $user]) : false;

            $organizer = $e->getOrganizer();

            // Stats
        $likesCount = $likeRepository->count(['event' => $e]);
        $commentsCount = $commentRepository->count(['event' => $e]);
        $bookmarksCount = $bookmarkRepository->count(['event' => $e]);
        $hasLiked = (bool)$likeRepository->findOneBy(['event' => $e, 'user' => $user]);
        $hasBookmarked = (bool)$bookmarkRepository->findOneBy(['event' => $e, 'user' => $user]);

            return [
                'id' => $e->getId(),
                'title' => $e->getTitle(),
                'description' => $e->getDescription(),
                'image' => $e->getImage(),
                'event_date' => $e->getEventDate()->format('Y-m-d'),
                'event_time' => $e->getEventTime()->format('H:i'),
                'event_location' => $e->getEventLocation(),
                'latitude' => $e->getLatitude(),      // ⬅️ AJOUTÉ
                'longitude' => $e->getLongitude(),
                'organizer' => $organizer ? [
                    'id' => $organizer->getId(),
                    'name' => $organizer->getName(), // Assurez-vous d'avoir le name sur l'entité User
                    'email' => $organizer->getEmail(),
                    'name' => $organizer->getName(),
                    'profilePicture' => $organizer->getProfilePicture(),
                ] : null,
                // ⭐ NOUVELLES DONNÉES D'INTERACTION
                 'likes_count' => $likesCount,
                 'comments_count' => $commentsCount,
                 'bookmarks_count' => $bookmarksCount,
                 'has_liked' => $hasLiked,
                 'has_bookmarked' => $hasBookmarked,
        ];
    }, $events);

        return new JsonResponse($data);
    }

    /** ------------------------------
     * ❌ SUPPRIMER UN ÉVÉNEMENT
     * ------------------------------ */
    #[Route('/{id}', name: 'api_delete_event', methods: ['DELETE'])]
    public function deleteEvent(
        Event $event,
        EntityManagerInterface $em,
        ActivityLogger $activityLogger
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['error' => 'Unauthorized'], 401);

        if ($event->getOrganizer()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        try {
            // Optionnel : Supprimer le fichier image du système de fichiers
            // $imagePath = $event->getImage();
            // if ($imagePath && file_exists($this->imageDirectory . '/' . $imagePath)) {
            //     unlink($this->imageDirectory . '/' . $imagePath);
            // }

            $activityLogger->log(
                'delete_event', 
                $user, 
                $event,
                null, 
                ['event_title' => $event->getTitle()] 
            );
            
            // Supprimer l’événement
            $em->remove($event);
            $em->flush();

          
            return new JsonResponse(['message' => 'Événement supprimé avec succès']);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la suppression de l’événement',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /** ------------------------------
     * ✏️ MODIFIER UN ÉVÉNEMENT
     * ------------------------------ */
    #[Route('/{id}', name: 'api_update_event', methods: ['POST', 'PUT'])]
    public function updateEvent(
        Request $request,
        Event $event,
        EntityManagerInterface $em,
        ActivityLogger $activityLogger
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user || $event->getOrganizer() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        // On récupère les données soit de la requête JSON (PUT), soit du form-data (POST/multipart)
        $data = $request->request->all();
        if (empty($data) && $request->getContentTypeFormat() === 'json') {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $imageFile = $request->files->get('image');

        // 🖼️ GESTION DE L'UPLOAD/MISE À JOUR D'IMAGE
        if ($imageFile instanceof UploadedFile) {
            try {
                // Optionnel: Supprimer l'ancienne image du système de fichiers
                // $oldImage = $event->getImage();
                // if ($oldImage && file_exists($this->imageDirectory . '/' . $oldImage)) {
                //     unlink($this->imageDirectory . '/' . $oldImage);
                // }

                $imagePath = $this->handleImageUpload($imageFile);
                $event->setImage($imagePath);
            } catch (\RuntimeException $e) {
                return new JsonResponse(['error' => $e->getMessage()], 500);
            }
        } elseif (array_key_exists('image', $data) && $data['image'] === null) {
            // L'utilisateur a explicitement demandé de supprimer l'image
            // Optionnel: Supprimer l'ancienne image du système de fichiers
            // ...
            $event->setImage(null);
        }

        // Mise à jour des autres propriétés
        $event->setTitle($data['title'] ?? $event->getTitle());
        $event->setDescription($data['description'] ?? $event->getDescription());
        if (isset($data['event_date'])) $event->setEventDate(new \DateTime($data['event_date']));
        if (isset($data['event_time'])) $event->setEventTime(new \DateTime($data['event_time']));
        $event->setEventLocation($data['event_location'] ?? $event->getEventLocation());
       
        // 📍 Mise à jour des coordonnées
        if (array_key_exists('latitude', $data)) $event->setLatitude($data['latitude'] !== null ? (float)$data['latitude'] : null);
        if (array_key_exists('longitude', $data)) $event->setLongitude($data['longitude'] !== null ? (float)$data['longitude'] : null);
        // 🔹 Log activité
        $activityLogger->log('update_event', $user, $event);

        $em->flush();

        return new JsonResponse(['message' => 'Event updated successfully']);
    }

    /** ------------------------------
     * 🟢 PARTAGER PUBLIQUEMENT / DÉ-PARTAGER UN ÉVÉNEMENT
     * ------------------------------ */
    #[Route('/{id}/share', name: 'api_toggle_share_event', methods: ['POST'])]
    public function toggleShareEvent(
        Event $event,
        EntityManagerInterface $em,
        ActivityLogger $activityLogger
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        // 🛑 Vérification des permissions
        if (!$user || $event->getOrganizer() !== $user) {
            return new JsonResponse(['error' => 'Accès refusé. Seul l\'organisateur peut partager.'], 403);
        }

        // 🔄 Basculer l'état de partage
        $newState = !$event->isPubliclyShared();
        $event->setIsPubliclyShared($newState);
        $em->flush();

        $action = $newState ? 'partagé publiquement' : 'retiré du partage public';
        $logAction = $newState ? 'share_event' : 'unshare_event';
        
        // 🔹 Log activité
       // $activityLogger->log($logAction, $user, $event);

        return new JsonResponse([
            'message' => "Événement {$action} avec succès.",
            'is_publicly_shared' => $newState
        ], 200);
    }

   /** ------------------------------
     * ❤️ LIKE / UNLIKE UN ÉVÉNEMENT
     * ------------------------------ */
    #[Route('/{id}/like', name: 'event_toggle_like', methods: ['POST'])]
    public function toggleLike(Event $event, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$event) {
            return new JsonResponse(['message' => 'Événement non trouvé.'], 404);
        }
        
        $likeRepository = $em->getRepository(Like::class);
        $existingLike = $likeRepository->findOneBy([
            'event' => $event,
            'user' => $user,
        ]);

        if ($existingLike) {
            // SUPPRIMER (Unlike)
            $em->remove($existingLike);
            $action = 'unliked';
            $hasLiked = false;
        } else {
            // CRÉER (Like)
            $newLike = new Like();
            $newLike->setEvent($event);
            $newLike->setUser($user);
            $em->persist($newLike);
            $action = 'liked';
            $hasLiked = true;
        }

        $em->flush();

        $newLikesCount = $likeRepository->count(['event' => $event]);

        return new JsonResponse([
            'message' => "Événement {$action} avec succès.",
            'action' => $action,
            'likes_count' => $newLikesCount,
            'has_liked' => $hasLiked,
        ], 200);
    }

    /** ------------------------------
     * 💬 AFFICHER LES COMMENTAIRES
     * ------------------------------ */
    #[Route('/{id}/comments', name: 'event_get_comments', methods: ['GET'])]
    public function getComments(Event $event, EntityManagerInterface $em): JsonResponse
    {
        if (!$event) {
            return new JsonResponse(['message' => 'Événement non trouvé.'], 404);
        }

        // Récupérer les commentaires avec les informations utilisateur triés par date
        $comments = $em->getRepository(Comment::class)->findBy(
            ['event' => $event],
            ['created_at' => 'DESC'] // Les plus récents en premier
        );

        $data = [];
        foreach ($comments as $comment) {
            $data[] = [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'created_at' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'user' => [
                    'id' => $comment->getUser()->getId(),
                    'name' => $comment->getUser()->getName(),
                    'profilePicture' => $comment->getUser()->getProfilePicture(),
                    // Ajoutez l'URL de la photo de profil si nécessaire
                ],
            ];
        }

        return new JsonResponse($data, 200);
    }

    /** ------------------------------
     * 📝 POSTER UN COMMENTAIRE
     * ------------------------------ */
    #[Route('/{id}/comments', name: 'event_post_comment', methods: ['POST'])]
    public function postComment(Event $event, Request $request, EntityManagerInterface $em, ActivityLogger $activityLogger): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$event) {
            return new JsonResponse(['message' => 'Événement non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (empty($content) || !is_string($content)) {
            return new JsonResponse(['message' => 'Le contenu du commentaire est requis.'], 400);
        }

        $comment = new Comment();
        $comment->setEvent($event);
        $comment->setUser($user);
        $comment->setContent(trim($content));

        $em->persist($comment);
        $em->flush();
        
        // Loggez l'activité de commentaire
        $activityLogger->log('comment_event', $user, $event);

        return new JsonResponse([
            'message' => 'Commentaire publié avec succès.',
            'commentId' => $comment->getId(),
            'comments_count' => $em->getRepository(Comment::class)->count(['event' => $event]),
        ], 201);
    }
     
    #[Route('/saved/{userId}', name: 'api_list_saved_events', methods: ['GET'])]
public function listSavedEvents(int $userId, EntityManagerInterface $em): JsonResponse
{
    $user = $em->getRepository(User::class)->find($userId);
    if (!$user) {
        return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
    }

    // Récupérer les événements que cet utilisateur a sauvegardés

    $likeRepository = $em->getRepository(Like::class);
    $commentRepository = $em->getRepository(Comment::class);
    $bookmarkRepository = $em->getRepository(Bookmark::class);

      // Récupérer les événements sauvegardés par cet utilisateur
    $bookmarks = $bookmarkRepository->findBy(['user' => $user]);
    $events = array_map(fn($b) => $b->getEvent(), $bookmarks);

    $data = array_map(function($e) use ($user, $likeRepository, $commentRepository, $bookmarkRepository) {
        $organizer = $e->getOrganizer();

          // Stats
        $likesCount = $likeRepository->count(['event' => $e]);
        $commentsCount = $commentRepository->count(['event' => $e]);
        $bookmarksCount = $bookmarkRepository->count(['event' => $e]);
        $hasLiked = (bool)$likeRepository->findOneBy(['event' => $e, 'user' => $user]);
        $hasBookmarked = true; // Comme c'est dans saved, toujours true

        return [
            'id' => $e->getId(),
            'title' => $e->getTitle(),
            'description' => $e->getDescription(),
            'image' => $e->getImage(),
            'event_date' => $e->getEventDate()->format('Y-m-d'),
            'event_time' => $e->getEventTime()->format('H:i'),
            'event_location' => $e->getEventLocation(),
            'latitude' => $e->getLatitude(),
            'longitude' => $e->getLongitude(),
            'organizer' => $organizer ? [
                'id' => $organizer->getId(),
                'name' => $organizer->getName(),
                'email' => $organizer->getEmail(),
                'profilePicture' => $organizer->getProfilePicture(),
            ] : null,
             'likes_count' => $likesCount,
            'comments_count' => $commentsCount,
            'bookmarks_count' => $bookmarksCount,
            'has_liked' => $hasLiked,
            'has_bookmarked' => $hasBookmarked,
        ];
    }, $events);

    return new JsonResponse($data);
}

#[Route('/{id}/bookmark', name:'event_toggle_bookmark', methods:['POST'])]
public function toggleBookmark(Event $event, EntityManagerInterface $em): JsonResponse
{
    /** @var User $user */
    $user = $this->getUser();

    $existingBookmark = $em->getRepository(Bookmark::class)->findOneBy([
        'event' => $event,
        'user' => $user,
    ]);

    if ($existingBookmark) {
        $em->remove($existingBookmark);
        $em->flush();
        return new JsonResponse(['message'=>'Bookmark removed','bookmarked'=>false]);
    } else {
        $bookmark = new Bookmark();
        $bookmark->setEvent($event);
        $bookmark->setUser($user);
        $em->persist($bookmark);
        $em->flush();
        return new JsonResponse(['message'=>'Event bookmarked','bookmarked'=>true]);
    }
}


   
}