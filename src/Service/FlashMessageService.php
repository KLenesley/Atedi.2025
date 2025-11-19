<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class FlashMessageService
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function addSuccess(string $message): void
    {
        $this->add('success', $message);
    }

    public function addError(string $message): void
    {
        $this->add('danger', $message); // 'danger' est la classe par défaut pour les erreurs dans Bootstrap
    }

    public function addInfo(string $message): void
    {
        $this->add('info', $message);
    }

    private function add(string $type, string $message): void
    {
        $this->requestStack->getSession()->getFlashBag()->add($type, $message);
    }
}
