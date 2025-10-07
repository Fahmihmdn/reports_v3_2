<?php

declare(strict_types=1);


header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

$filters = [
    'startDate' => getDateParam('startDate'),
    'endDate' => getDateParam('endDate'),
];

$pdo = Database::tryConnect();

if ($pdo === null) {
    $reports = buildStaticReportsPayload($filters);

    echo json_encode([
        'filters' => $filters,
        'data' => $reports,
    ]);
    exit;
}

$reports = buildReportsPayload($pdo, $filters);

$response = [
    'filters' => $filters,
    'data' => $reports,
];

echo json_encode($response);

function getDateParam(string $key): ?string
{
    $value = $_GET[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTime ? $date->format('Y-m-d') : null;
}

function buildReportsPayload(PDO $pdo, array $filters): array
{
    $startDate = $filters['startDate'] ?? '1900-01-01';
    $endDate = $filters['endDate'] ?? '2100-12-31';

    $reportData = [
        buildDisbursementReport($pdo, $startDate, $endDate),
        buildRepaymentReport($pdo, $startDate, $endDate),
        buildUpcomingScheduleReport($pdo, $endDate),
    ];

    return [
        'reports' => $reportData,
    ];
}

function buildStaticReportsPayload(array $filters): array
{
    $startDate = $filters['startDate'] ?? '1900-01-01';
    $endDate = $filters['endDate'] ?? '2100-12-31';

    $reports = [
        buildStaticDisbursementReport($startDate, $endDate),
        buildStaticRepaymentReport($startDate, $endDate),
        buildStaticUpcomingScheduleReport($endDate),
    ];

    return [
        'reports' => $reports,
    ];
}

function buildReportUrl(string $identifier): string
{
    return '#report-' . $identifier;
}

function buildDisbursementReport(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = <<<SQL
        SELECT
            COUNT(DISTINCT d.id) AS disbursement_count,
            COALESCE(SUM(d.amount), 0) AS total_disbursed,
            COALESCE(AVG(d.amount), 0) AS average_disbursement
        FROM disbursements d
        WHERE d.date BETWEEN :startDate AND :endDate
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $result = $statement->fetch() ?: [
        'disbursement_count' => 0,
        'total_disbursed' => 0,
        'average_disbursement' => 0,
    ];

    return [
        'id' => 'loan-disbursement-summary',
        'name' => 'Loan Disbursement Summary',
        'description' => 'Overview of disbursement activity within the selected period.',
        'url' => buildReportUrl('loan-disbursement-summary'),
        'metrics' => [
            [
                'label' => 'Total Disbursed',
                'value' => (float) $result['total_disbursed'],
                'formatted' => number_format((float) $result['total_disbursed'], 2),
            ],
            [
                'label' => 'Disbursement Count',
                'value' => (int) $result['disbursement_count'],
                'formatted' => (int) $result['disbursement_count'],
            ],
            [
                'label' => 'Average Disbursement',
                'value' => (float) $result['average_disbursement'],
                'formatted' => number_format((float) $result['average_disbursement'], 2),
            ],
        ],
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildRepaymentReport(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = <<<SQL
        SELECT
            COUNT(DISTINCT r.id) AS repayment_count,
            COALESCE(SUM(r.amount), 0) AS total_repayment,
            COALESCE(SUM(r.principal), 0) AS total_principal,
            COALESCE(SUM(r.interest), 0) AS total_interest
        FROM repayments r
        WHERE r.date BETWEEN :startDate AND :endDate
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $result = $statement->fetch() ?: [
        'repayment_count' => 0,
        'total_repayment' => 0,
        'total_principal' => 0,
        'total_interest' => 0,
    ];

    return [
        'id' => 'repayment-performance',
        'name' => 'Repayment Performance',
        'description' => 'Payments received from borrowers across the selected period.',
        'url' => buildReportUrl('repayment-performance'),
        'metrics' => [
            [
                'label' => 'Total Repaid',
                'value' => (float) $result['total_repayment'],
                'formatted' => number_format((float) $result['total_repayment'], 2),
            ],
            [
                'label' => 'Principal Repaid',
                'value' => (float) $result['total_principal'],
                'formatted' => number_format((float) $result['total_principal'], 2),
            ],
            [
                'label' => 'Interest Repaid',
                'value' => (float) $result['total_interest'],
                'formatted' => number_format((float) $result['total_interest'], 2),
            ],
            [
                'label' => 'Repayment Count',
                'value' => (int) $result['repayment_count'],
                'formatted' => (int) $result['repayment_count'],
            ],
        ],
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildUpcomingScheduleReport(PDO $pdo, string $endDate): array
{
    $sql = <<<SQL
        SELECT
            COUNT(DISTINCT ps.id) AS upcoming_payments,
            COALESCE(SUM(ps.amount), 0) AS scheduled_amount
        FROM payment_schedule ps
        WHERE ps.date > CURRENT_DATE()
            AND ps.date <= :endDate
            AND ps.skip = 0
            AND ps.deleted = 0
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'endDate' => $endDate,
    ]);

    $result = $statement->fetch() ?: [
        'upcoming_payments' => 0,
        'scheduled_amount' => 0,
    ];

    return [
        'id' => 'upcoming-payment-schedule',
        'name' => 'Upcoming Payment Schedule',
        'description' => 'Payments scheduled after today up to the selected end date.',
        'url' => buildReportUrl('upcoming-payment-schedule'),
        'metrics' => [
            [
                'label' => 'Scheduled Amount',
                'value' => (float) $result['scheduled_amount'],
                'formatted' => number_format((float) $result['scheduled_amount'], 2),
            ],
            [
                'label' => 'Upcoming Payments',
                'value' => (int) $result['upcoming_payments'],
                'formatted' => (int) $result['upcoming_payments'],
            ],
        ],
        'suggestedFilters' => ['endDate'],
    ];
}

function buildStaticDisbursementReport(string $startDate, string $endDate): array
{
    $disbursements = getStaticDisbursements();

    $count = 0;
    $total = 0.0;

    foreach ($disbursements as $disbursement) {
        $date = $disbursement['date'];
        if ($date < $startDate || $date > $endDate) {
            continue;
        }

        $count++;
        $total += $disbursement['amount'];
    }

    $average = $count > 0 ? $total / $count : 0.0;

    return [
        'id' => 'loan-disbursement-summary',
        'name' => 'Loan Disbursement Summary',
        'description' => 'Overview of disbursement activity within the selected period.',
        'url' => buildReportUrl('loan-disbursement-summary'),
        'metrics' => [
            [
                'label' => 'Total Disbursed',
                'value' => $total,
                'formatted' => number_format($total, 2),
            ],
            [
                'label' => 'Disbursement Count',
                'value' => $count,
                'formatted' => $count,
            ],
            [
                'label' => 'Average Disbursement',
                'value' => $average,
                'formatted' => number_format($average, 2),
            ],
        ],
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildStaticRepaymentReport(string $startDate, string $endDate): array
{
    $repayments = getStaticRepayments();

    $count = 0;
    $total = 0.0;
    $totalPrincipal = 0.0;
    $totalInterest = 0.0;

    foreach ($repayments as $repayment) {
        $date = $repayment['date'];
        if ($date < $startDate || $date > $endDate) {
            continue;
        }

        $count++;
        $total += $repayment['amount'];
        $totalPrincipal += $repayment['principal'];
        $totalInterest += $repayment['interest'];
    }

    return [
        'id' => 'repayment-performance',
        'name' => 'Repayment Performance',
        'description' => 'Payments received from borrowers across the selected period.',
        'url' => buildReportUrl('repayment-performance'),
        'metrics' => [
            [
                'label' => 'Total Repaid',
                'value' => $total,
                'formatted' => number_format($total, 2),
            ],
            [
                'label' => 'Principal Repaid',
                'value' => $totalPrincipal,
                'formatted' => number_format($totalPrincipal, 2),
            ],
            [
                'label' => 'Interest Repaid',
                'value' => $totalInterest,
                'formatted' => number_format($totalInterest, 2),
            ],
            [
                'label' => 'Repayment Count',
                'value' => $count,
                'formatted' => $count,
            ],
        ],
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildStaticUpcomingScheduleReport(string $endDate): array
{
    $schedules = getStaticPaymentSchedules();

    $today = (new DateTimeImmutable('now'))->format('Y-m-d');

    $count = 0;
    $total = 0.0;

    foreach ($schedules as $schedule) {
        if (($schedule['skip'] ?? 0) === 1 || ($schedule['deleted'] ?? 0) === 1) {
            continue;
        }

        $date = $schedule['date'];
        if ($date <= $today || $date > $endDate) {
            continue;
        }

        $count++;
        $total += $schedule['amount'];
    }

    return [
        'id' => 'upcoming-payment-schedule',
        'name' => 'Upcoming Payment Schedule',
        'description' => 'Payments scheduled after today up to the selected end date.',
        'url' => buildReportUrl('upcoming-payment-schedule'),
        'metrics' => [
            [
                'label' => 'Scheduled Amount',
                'value' => $total,
                'formatted' => number_format($total, 2),
            ],
            [
                'label' => 'Upcoming Payments',
                'value' => $count,
                'formatted' => $count,
            ],
        ],
        'suggestedFilters' => ['endDate'],
    ];
}

function getStaticDisbursements(): array
{
    return [
        ['id' => 5001, 'application_id' => 1001, 'date' => '2024-03-05', 'amount' => 5000.0],
        ['id' => 5002, 'application_id' => 1002, 'date' => '2024-04-20', 'amount' => 12000.0],
    ];
}

function getStaticRepayments(): array
{
    return [
        ['id' => 8001, 'application_id' => 1001, 'date' => '2024-04-04', 'amount' => 2600.0, 'principal' => 2500.0, 'interest' => 100.0],
        ['id' => 8002, 'application_id' => 1002, 'date' => '2024-05-19', 'amount' => 6100.0, 'principal' => 6000.0, 'interest' => 100.0],
    ];
}

function getStaticPaymentSchedules(): array
{
    return [
        ['id' => 7001, 'application_id' => 1001, 'date' => '2024-04-05', 'amount' => 2600.0, 'skip' => 0, 'deleted' => 0],
        ['id' => 7002, 'application_id' => 1001, 'date' => '2024-05-05', 'amount' => 2600.0, 'skip' => 0, 'deleted' => 0],
        ['id' => 7003, 'application_id' => 1002, 'date' => '2024-05-20', 'amount' => 6100.0, 'skip' => 0, 'deleted' => 0],
    ];
}
