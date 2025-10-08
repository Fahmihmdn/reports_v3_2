<?php

declare(strict_types=1);

if (!function_exists('getStaticDisbursements')) {
    function getStaticDisbursements(): array
    {
        return [
            ['id' => 5001, 'application_id' => 1001, 'date' => '2024-03-05', 'amount' => 5000.0],
            ['id' => 5002, 'application_id' => 1002, 'date' => '2024-04-20', 'amount' => 12000.0],
        ];
    }
}

if (!function_exists('getStaticRepayments')) {
    function getStaticRepayments(): array
    {
        return [
            ['id' => 8001, 'application_id' => 1001, 'date' => '2024-04-04', 'amount' => 2600.0, 'principal' => 2500.0, 'interest' => 100.0],
            ['id' => 8002, 'application_id' => 1002, 'date' => '2024-05-19', 'amount' => 6100.0, 'principal' => 6000.0, 'interest' => 100.0],
        ];
    }
}

if (!function_exists('getStaticPaymentSchedules')) {
    function getStaticPaymentSchedules(): array
    {
        return [
            ['id' => 7001, 'application_id' => 1001, 'date' => '2024-04-05', 'amount' => 2600.0, 'skip' => 0, 'deleted' => 0],
            ['id' => 7002, 'application_id' => 1001, 'date' => '2024-05-05', 'amount' => 2600.0, 'skip' => 0, 'deleted' => 0],
            ['id' => 7003, 'application_id' => 1002, 'date' => '2024-05-20', 'amount' => 6100.0, 'skip' => 0, 'deleted' => 0],
        ];
    }
}
