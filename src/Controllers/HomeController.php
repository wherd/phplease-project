<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Routing\Controller;
use Phplease\Foundation\Application;
use Phplease\Http\Response;

class HomeController extends Controller
{
    #[\App\Routing\Route('GET', '/')]
    public function index(): Response
    {
        /** @var \Signal\View */
        $view = Application::getInstance()->providerOf('view');

        $this->response->setStatusCode(200);
        $this->response->html($view->render('/home/index'));

        return $this->response;
    }
}
