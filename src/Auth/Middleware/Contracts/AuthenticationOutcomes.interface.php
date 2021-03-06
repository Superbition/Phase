<?php

namespace Polyel\Auth\Middleware\Contracts;

interface AuthenticationOutcomes
{
    public function unauthenticated();

    public function authenticated();

    public function unauthorized();

    public function authorized();
}