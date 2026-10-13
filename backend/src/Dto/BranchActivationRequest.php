<?php

namespace App\Dto;

final readonly class BranchActivationRequest
{
    public function __construct(
        public bool $active = true,
    ) {
    }
}
