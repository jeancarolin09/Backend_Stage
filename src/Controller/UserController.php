<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerifier;
use App\Service\ProfilePictureService; 
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use \DateTimeImmutable; // Importation de DateTimeImmutable
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UserController extends AbstractController
{
    private EmailVerifier $emailVerifier;
    private EntityManagerInterface $em;
    private JWTTokenManagerInterface $jwtManager;
    private ProfilePictureService $profilePictureService;
    private ParameterBagInterface $parameterBag;
    private UserRepository $userRepo;
    // Injection des dépendances dans le constructeur
    public function __construct(
        EmailVerifier $emailVerifier, 
        EntityManagerInterface $em, 
        JWTTokenManagerInterface $jwtManager,
        ProfilePictureService $profilePictureService, 
        ParameterBagInterface $parameterBag,
        UserRepository $userRepo
    ) {
        $this->emailVerifier = $emailVerifier;
        $this->em = $em;
        $this->jwtManager = $jwtManager;
        $this->profilePictureService = $profilePictureService; // <-- ASSIGNATION
        $this->parameterBag = $parameterBag;
        $this->userRepo = $userRepo; 
    }
    
     #[Route('/api/users/search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json(['error' => 'Query must be at least 2 characters'], 400);
        }

        // Rechercher par nom ou email (limitez à 20 résultats)
        $users = $this->userRepo->searchByNameOrEmail($query);

        $data = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'profilePictureUrl' => $this->profilePictureService->getProfilePictureUrl($user),
                'isVerified' => $user->isVerified(),
            ];
        }, $users);

        return $this->json($data);
    }

    #[Route('/api/users/{id}', methods: ['GET'])]
    public function getUserById(int $id): JsonResponse // ✅ Renommez la méthode
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'profilePictureUrl' => $this->profilePictureService->getProfilePictureUrl($user),
            'isVerified' => $user->isVerified(),
        ]);
    }

    #[Route('/api/users', name: 'create_user', methods: ['POST'])]
    public function createUser(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['message' => 'Données manquantes (name, email, password)'], 400);
        }

        // Vérification de l'existence de l'email
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return new JsonResponse(['message' => 'Email déjà utilisé'], 400);
        }

        $user = new User();
        $user->setName($data['name']);
        $user->setEmail($data['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setIsVerified(false);
        
        // ⭐ LOGIQUE OTP : Génération du code à 6 chiffres et de l'expiration (10 minutes)
        try {
            // Génère un code entre 100000 et 999999
            $code = (string) random_int(100000, 999999); 
        } catch (\Exception $e) {
            // Fallback si random_int échoue
            $code = substr(str_shuffle('0123456789'), 0, 6);
        }
        
        $expiration = (new DateTimeImmutable())->modify('+10 minutes');
        
        $user->setVerificationCode($code);
        $user->setCodeExpiresAt($expiration);
        
        $this->em->persist($user);
        $this->em->flush();

        // Génération du JWT de connexion initiale (l'utilisateur est connecté mais non vérifié)
        $jwt = $this->jwtManager->create($user); 

        // Envoi du mail contenant UNIQUEMENT le code
        $this->emailVerifier->sendVerificationCode($user->getEmail(), $user->getName(), $code);


        return new JsonResponse([
            'message' => 'Utilisateur créé. Un code de vérification à 6 chiffres a été envoyé à votre adresse email.',
            'token' => $jwt, 
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'profilePictureUrl' => $this->profilePictureService->getProfilePictureUrl($user), 
            ]
        ], 201);
    }

    #[Route('/api/verify-code', name: 'verify_code', methods: ['POST'])]
    public function verifyCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['code'])) {
            return new JsonResponse(['message' => 'Email ou code manquant'], 400);
        }
        
        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user) {
            return new JsonResponse(['message' => 'Utilisateur non trouvé'], 404);
        }

        // 1. Vérification du statut (si déjà vérifié, renvoyer un succès)
        if ($user->isVerified()) {
            return new JsonResponse(['message' => 'Compte déjà vérifié.'], 200);
        }

        // 2. Vérification de l'expiration du code
        $now = new DateTimeImmutable();
        if (!$user->getCodeExpiresAt() || $user->getCodeExpiresAt() < $now) {
            return new JsonResponse(['message' => 'Code expiré. Veuillez demander un nouvel envoi.'], 400);
        }

        // 3. Vérification de la correspondance du code
        if ($user->getVerificationCode() !== $data['code']) {
            return new JsonResponse(['message' => 'Code de vérification invalide.'], 400);
        }

        // 4. Succès : Validation et nettoyage
        $user->setIsVerified(true);
        $user->setVerificationCode(null); // Nettoyage du code
        $user->setCodeExpiresAt(null); // Nettoyage de l'expiration
        $this->em->flush();
        
        // 5. Génération d'un NOUVEAU JWT pour s'assurer que l'état 'isVerified: true' est dans le token
        $newJwt = $this->jwtManager->create($user); 

        return new JsonResponse([
            'message' => 'Compte vérifié avec succès. Vous êtes maintenant connecté.',
            'token' => $newJwt, // Renvoyer le nouveau token vérifié
        ], 200);
    }
    
    #[Route('/api/users/me', name: 'api_me', methods: ['GET'])] 
    public function me(): JsonResponse
    {
        // $this->getUser() est fourni par AbstractController et récupère l'utilisateur à partir du JWT.
        $user = $this->getUser(); 
        
        if (!$user instanceof User) { // Vérification de l'instance
            return new JsonResponse(['message' => 'Utilisateur non connecté ou jeton invalide'], 401);
        }
         
         // ⭐ UTILISATION DU SERVICE : Construction de l'URL complète pour la réponse
        $profilePictureUrl = $this->profilePictureService->getProfilePictureUrl($user);

         
        return new JsonResponse([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'profilePictureUrl' => $profilePictureUrl, 
            'isVerified' => $user->isVerified(), // Ajout du statut de vérification
        ]);
    }
    
    #[Route('/api/users/update', name: 'update_user', methods: ['POST'])]
    public function updateUser(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Utilisateur non connecté'], 401);
        }
        
        // Les données peuvent venir de form-data (avec fichier) ou de JSON (sans fichier).
        // Si le type de contenu est 'application/json', on décode le contenu.
        $contentType = $request->headers->get('Content-Type');
        $data = [];
        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);
        } else {
            // Pour les requêtes multipart/form-data (avec fichier), on utilise request->get()
            $data = $request->request->all();
        }
        
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        
        // Fichier (uniquement présent en multipart/form-data)
        $file = $request->files->get('profilePicture');

        if ($name) $user->setName($name);
        
        if ($email && $email !== $user->getEmail()) {
             // Vérification rapide de non-conflit lors du changement d'email
             if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
                 return new JsonResponse(['message' => 'Ce nouvel email est déjà utilisé par un autre compte.'], 400);
             }
             $user->setEmail($email);
             // Optionnel : Réinitialiser la vérification si l'email change
             // $user->setIsVerified(false);
             // $this->em->flush();
             // return new JsonResponse(['message' => 'Email mis à jour. Veuillez le revérifier.'], 200);
        }
        
        if ($password) {
            $user->setPassword($passwordHasher->hashPassword($user, $password));
        }

        if ($file) {
            // Le paramètre 'kernel.project_dir' doit être injecté dans le service si l'on n'est pas dans AbstractController
            // Mais dans AbstractController, getParameter est disponible.
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile';
            $newFilename = uniqid() . '.' . $file->guessExtension();

            try {
                // S'assurer que le répertoire existe (bonne pratique)
                if (!file_exists($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                
                $file->move($uploadsDir, $newFilename);
                // Stocker le chemin relatif pour l'accès web
                $user->setProfilePicture('/uploads/profile/' . $newFilename); 
            } catch (FileException $e) {
                return new JsonResponse(['message' => 'Erreur lors du téléversement du fichier: ' . $e->getMessage()], 500);
            }
        }

        $this->em->flush();

         // ⭐ UTILISATION DU SERVICE : Construction de l'URL complète pour la réponse
        $profilePictureUrl = $this->profilePictureService->getProfilePictureUrl($user);

        return new JsonResponse([
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'profilePictureUrl' => $profilePictureUrl,
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }
    
    #[Route('/api/users', methods: ['GET'])]
public function getUsers(UserRepository $repo): JsonResponse
{
    $users = $repo->findAll();

    $data = array_map(fn($u) => [
        'id' => $u->getId(),
        'name' => $u->getName(),
        'email' => $u->getEmail(),
        'profilePictureUrl' => $u->getProfilePicture(),
        'isOnline' => $u->getIsOnline(),
        'lastActivity' => $u->getLastActivity()?->format('Y-m-d H:i:s'),
    ], $users);

    return new JsonResponse($data);
}



}