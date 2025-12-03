<?php

namespace App\Service;

use App\Entity\User;

/**
 * Service pour gérer les chemins d'accès et les URLs des images de profil.
 */
class ProfilePictureService
{
    private string $baseUrl;
    private string $defaultPicture = '/img/default_profile.jpg'; // Image par défaut

    /**
     * @param string $baseUrl L'URL de base de l'application (e.g., http://localhost:8000)
     */
    public function __construct(string $baseUrl)
    {
        // Supprime la barre oblique finale si elle est présente pour une construction d'URL propre
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Construit l'URL complète de l'image de profil pour un utilisateur donné.
     * Utilise une image par défaut si le chemin n'est pas défini.
     * * @param User|null $user L'objet utilisateur.
     * @return string L'URL complète de l'image.
     */
    public function getProfilePictureUrl(?User $user): string
    {
        $filePath = $user ? $user->getProfilePicture() : null;

        if ($filePath) {
            // Le chemin de la base de données commence par /uploads/profile/...
            return $this->baseUrl . $filePath;
        }

        // Renvoie l'image par défaut si aucune image n'est définie
        return $this->baseUrl . $this->defaultPicture;
    }
}