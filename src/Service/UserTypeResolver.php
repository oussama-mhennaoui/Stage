<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Etudiant;
use App\Entity\Diplome;
use App\Entity\Enseignant;
use App\Entity\Entreprise;

class UserTypeResolver
{
    private const ROLE_MAP = [
        'ROLE_ETUDIANT' => Etudiant::class,
        'ROLE_DIPLOME' => Diplome::class,
        'ROLE_ENSEIGNANT' => Enseignant::class,
        'ROLE_ENTREPRISE' => Entreprise::class,
        'ROLE_USER' => User::class,
    ];

    public function resolveUserType(User $user): string
    {
        foreach ($user->getRoles() as $role) {
            if (isset(self::ROLE_MAP[$role])) {
                return self::ROLE_MAP[$role];
            }
        }
        
        return User::class;
    }

    public function getUserTypeClass(string $userType): string
    {
        return match ($userType) {
            'etudiant' => Etudiant::class,
            'diplome' => Diplome::class,
            'enseignant' => Enseignant::class,
            'entreprise' => Entreprise::class,
            default => User::class,
        };
    }
}
