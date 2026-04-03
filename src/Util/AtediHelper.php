<?php

namespace App\Util;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class AtediHelper
{
    public function strTotalPrice($intervention) 
    {
        $totalEuro = 0;
        $totalCents = 0;

        $tasksCollection = $intervention->getTasks();
        foreach ( $tasksCollection as $task ) {
            
            $price = $task->getPrice();

            if (strpos($price,'.')) {
                $delimiter = '.';
            } elseif (strpos($price,',')) {
                $delimiter = ',';
            } else {
                $delimiter = " ";
            }

            $taskPrice = explode($delimiter,$price);

            if (count($taskPrice) > 1) {
                $taskEuro = intval($taskPrice[0]);
                $taskCents = intval($taskPrice[1]);
                if (strlen($taskPrice[1]) === 1) {
                    $taskCents *= 10;
                }
            } else {
                $taskEuro = intval($taskPrice[0]);
                $taskCents = 0;
            }

            $totalEuro += $taskEuro;
            $totalCents += $taskCents;

            if ( $totalCents >= 100 ) {
                $totalEuro++;
                $totalCents -= 100;
            }
        }


        $billingLinesCollection = $intervention->getBillingLines();
        foreach ( $billingLinesCollection as $billingLine ) {

            $price = $billingLine->getPrice();
            
            if (strpos($price,'.')) {
                $delimiter = '.';
            } elseif (strpos($price,',')) {
                $delimiter = ',';
            } else {
                $delimiter = " ";
            }

            $billingLinePrice = explode($delimiter,$price);

            if (count($billingLinePrice) > 1) {
                $billingLineEuro = intval($billingLinePrice[0]);
                $billingLineCents = intval($billingLinePrice[1]);
                if (strlen($billingLinePrice[1]) === 1) {
                    $billingLineCents *= 10;
                }
            } else {
                $billingLineEuro = intval($billingLinePrice[0]);
                $billingLineCents = 0;
            }

            $totalEuro += $billingLineEuro;
            $totalCents += $billingLineCents;

            if ( $totalCents >= 100 ) {
                $totalEuro++;
                $totalCents -= 100;
            }
        }

        $totalEuro = strval($totalEuro);
        $totalCents = strval($totalCents);
        if (strlen($totalCents) === 1) {
            $totalCents = '0'.$totalCents;
        }

        return $totalEuro.','.$totalCents;
    }
}