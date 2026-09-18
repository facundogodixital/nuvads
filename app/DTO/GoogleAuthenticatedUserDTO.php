<?php

namespace App\DTO;

class GoogleAuthenticatedUserDTO
{


    public function __construct(
        public readonly string $googleId,
        public readonly string $email,
        public readonly string $name,
    ) {}

}
