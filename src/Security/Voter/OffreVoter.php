<?php

namespace App\Security\Voter;

use App\Entity\Entreprise;
use App\Entity\Offre;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class OffreVoter extends Voter
{
    public const EDIT = 'edit';
    public const VIEW = 'view';
    public const DELETE = 'delete';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW, self::DELETE])
            && $subject instanceof \App\Entity\Offre;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof Entreprise) {
            return false;
        }

        /** @var Offre $offre */
        $offre = $subject;

        switch ($attribute) {
            case self::EDIT:
            case self::VIEW:
            case self::DELETE:
                return $offre->getEntreprise() === $user;
        }

        return false;
    }
}
