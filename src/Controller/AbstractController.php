<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseAbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

abstract class AbstractController extends BaseAbstractController
{
    /**
     * Throws an exception unless the user's email is verified.
     *
     * @throws AccessDeniedException If the user is not verified
     */
    protected function denyAccessUnlessVerified(): void
    {
        $user = $this->getUser();
        
        if (!$user instanceof User || !$user->isVerified()) {
            throw $this->createAccessDeniedException('Please verify your email address to access this page.');
        }
    }
}
