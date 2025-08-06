<?php

namespace App\Entity\Enum;

/**
 * Enum representing the type of offer.
 */
enum TypeOffre: string
{
    case STAGE_ETE = 'stage été';
    case STAGE_PFE = 'stage pfe';
    case EMPLOIS    = 'emplois';
}
