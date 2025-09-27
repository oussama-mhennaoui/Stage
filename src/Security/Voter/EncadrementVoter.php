<?php

namespace App\Security\Voter;

use App\Entity\Encadrement;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EncadrementVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Encadrement;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        // If the user is not logged in, deny access
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var Encadrement $encadrement */
        $encadrement = $subject;

        // Check if the user is an admin
        if ($this->isAdmin($user)) {
            return true;
        }

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($encadrement, $user);
            case self::EDIT:
                return $this->canEdit($encadrement, $user);
            case self::DELETE:
                return $this->canDelete($encadrement, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(Encadrement $encadrement, User $user): bool
    {
        // The student or teacher can view the encadrement
        return $encadrement->getEtudiant() === $user || 
               $encadrement->getEnseignant() === $user ||
               $this->isAdmin($user);
    }

    private function canEdit(Encadrement $encadrement, User $user): bool
    {
        // Only the owner student can edit their request
        return $encadrement->getEtudiant() === $user || $this->isAdmin($user);
    }

    private function canDelete(Encadrement $encadrement, User $user): bool
    {
        // Only the owner student or admin can delete the request
        return $encadrement->getEtudiant() === $user || $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
