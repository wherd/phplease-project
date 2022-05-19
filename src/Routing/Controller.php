<?php

declare(strict_types=1);

namespace App\Routing;

use Phplease\Http\Response;
use Phplease\Http\Request;

abstract class Controller
{
    public function __construct(protected Request $request, protected Response $response)
    {
    }
}
