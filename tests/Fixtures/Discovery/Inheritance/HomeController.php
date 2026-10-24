<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Fixtures\Discovery\Inheritance;

use Mithridatem\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public static int $constructed = 0;

    public function __construct()
    {
        ++self::$constructed;
    }

    #[Route(path: '/inherited-home', method: 'GET')]
    public function home(): string
    {
        return $this->render('home');
    }
}
