<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EmailVerifiedVoter extends Voter
{
    protected function supports(string $attribute, $subject): bool
    {
        // Only vote on IS_VERIFIED attributes and User objects
        return $attribute === 'IS_VERIFIED' && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // The user must be logged in
        if (!$user instanceof User) {
            return false;
        }

        // Check if the user's email is verified
        return $user->isVerified();
    }
}
