<?php

namespace App\Twig;

use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class AppExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('intervStatus', [$this, 'statusFinder']),
            new TwigFunction('isWindows', [$this, 'windowsFinder']),
            new TwigFunction('completePrice', [$this, 'completePrice']),
        ];
    }

    public function statusFinder($theStatus)
    {
        switch ($theStatus) {
            case 'En attente':
                return 'waiting';

            case 'En cours':
                return 'active';

            case 'Terminée':
                return 'finished';
        }
        return null;
    }

    public function windowsFinder($theOperatingSystem)
    {
        $isWindows = false;

        if (preg_match('/Windows|windows/', $theOperatingSystem)) {
            $isWindows = true;
        }
        return $isWindows;
    }

    public function completePrice($price)
    {
        if (strpos($price,'.')) {
            $delimiter = '.';
        } elseif (strpos($price,',')) {
            $delimiter = ',';
        } else {
            $delimiter = " ";
        }

        $taskPrice = explode($delimiter,$price);
        $taskEuro = $taskPrice[0];

        if (count($taskPrice) > 1) {
            $taskCents = intval($taskPrice[1]);
            if (strlen($taskPrice[1]) === 1) {
                $taskCents *= 10;
            }
        } else {
            $taskCents = 0;
        }

        $taskCents = strval($taskCents);
        if (strlen($taskCents) === 1) {
            $taskCents = '0'.$taskCents;
        }

        return $taskEuro.','.$taskCents;
    }
}