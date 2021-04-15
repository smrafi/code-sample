<?php

namespace App\Services\Migration;

use App\Exceptions\BadRequestException;

class MigrationException extends BadRequestException
{
    /**
     * @param string $group
     * @return static
     */
    public static function becauseGroupIsInvalid(string $group)
    {
        $badRequestException = new static();
        $badRequestException->setDetail(
            sprintf('The provided group %s is not a valid migrations group', $group)
        );
        return $badRequestException;
    }

    /**
     * @param string $group
     * @return static
     */
    public static function becauseNoDatabaseFound(string $group)
    {
        $badRequestException = new static();
        $badRequestException->setDetail(
            sprintf('The provided group %s is not having any valid databases', $group)
        );
        return $badRequestException;
    }
}
