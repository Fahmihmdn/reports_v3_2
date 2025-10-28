<?php

declare(strict_types=1);


header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/static_data.php';

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
    $startDateFilter = $filters['startDate'] ?? null;
    $endDateFilter = $filters['endDate'] ?? null;

    $startDate = $startDateFilter ?? '1900-01-01';
    $endDate = $endDateFilter ?? '2100-12-31';

    $reportData = [
        buildDisbursementReport($pdo, $startDate, $endDate, $startDateFilter, $endDateFilter),
        buildRepaymentReport($pdo, $startDate, $endDate, $startDateFilter, $endDateFilter),
        buildUpcomingScheduleReport($pdo, $startDate, $endDate, $startDateFilter, $endDateFilter),
        buildAllLoansReport($pdo, $startDate, $endDate, $startDateFilter, $endDateFilter),
        buildBorrowerListReport($pdo, $startDate, $endDate, $startDateFilter, $endDateFilter),
    ];

    return [
        'reports' => $reportData,
    ];
}

function buildStaticReportsPayload(array $filters): array
{
    $startDateFilter = $filters['startDate'] ?? null;
    $endDateFilter = $filters['endDate'] ?? null;

    $startDate = $startDateFilter ?? '1900-01-01';
    $endDate = $endDateFilter ?? '2100-12-31';

    $reports = [
        buildStaticDisbursementReport($startDate, $endDate, $startDateFilter, $endDateFilter),
        buildStaticRepaymentReport($startDate, $endDate, $startDateFilter, $endDateFilter),
        buildStaticUpcomingScheduleReport($startDate, $endDate, $startDateFilter, $endDateFilter),
        buildStaticAllLoansReport($startDate, $endDate, $startDateFilter, $endDateFilter),
        buildStaticBorrowerListReport($startDate, $endDate, $startDateFilter, $endDateFilter),
    ];

    return [
        'reports' => $reports,
    ];
}

function buildDisbursementSummaryUrl(?string $startDate, ?string $endDate): string
{
    $params = [];

    if ($startDate) {
        $params['startDate'] = $startDate;
    }

    if ($endDate) {
        $params['endDate'] = $endDate;
    }

    $basePath = '/backend/reports/disbursement-summary.php';

    if (empty($params)) {
        return $basePath;
    }

    return $basePath . '?' . http_build_query($params);
}

function buildRepaymentPerformanceUrl(?string $startDate, ?string $endDate): string
{
    $params = [];

    if ($startDate) {
        $params['startDate'] = $startDate;
    }

    if ($endDate) {
        $params['endDate'] = $endDate;
    }

    $basePath = '/backend/reports/repayment-performance.php';

    if (empty($params)) {
        return $basePath;
    }

    return $basePath . '?' . http_build_query($params);
}

function buildReportUrl(string $identifier): string
{
    return '#report-' . $identifier;
}

function buildUpcomingScheduleUrl(?string $startDate, ?string $endDate): string
{
    $params = [];

    if ($startDate) {
        $params['startDate'] = $startDate;
    }

    if ($endDate) {
        $params['endDate'] = $endDate;
    }

    $basePath = '/backend/reports/upcoming-payment-schedule.php';

    if (empty($params)) {
        return $basePath;
    }

    return $basePath . '?' . http_build_query($params);
}

function buildAllLoansReportUrl(?string $startDate, ?string $endDate): string
{
    $params = [];

    if ($startDate) {
        $params['startDate'] = $startDate;
    }

    if ($endDate) {
        $params['endDate'] = $endDate;
    }

    $basePath = '/backend/reports/all-loans-report.php';

    if (empty($params)) {
        return $basePath;
    }

    return $basePath . '?' . http_build_query($params);
}

function buildBorrowerListUrl(?string $startDate, ?string $endDate): string
{
    $params = [];

    if ($startDate) {
        $params['startDate'] = $startDate;
    }

    if ($endDate) {
        $params['endDate'] = $endDate;
    }

    $basePath = '/backend/reports/borrower-list.php';

    if (empty($params)) {
        return $basePath;
    }

    return $basePath . '?' . http_build_query($params);
}

function buildDisbursementReport(PDO $pdo, string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
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
        'url' => buildDisbursementSummaryUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
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

function buildRepaymentReport(PDO $pdo, string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
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
        'url' => buildRepaymentPerformanceUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
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

function buildUpcomingScheduleReport(PDO $pdo, string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
{
    $sql = <<<SQL
        SELECT
            COUNT(DISTINCT ps.id) AS upcoming_payments,
            COALESCE(SUM(ps.amount), 0) AS scheduled_amount
        FROM payment_schedule ps
        WHERE ps.date BETWEEN :startDate AND :endDate
            AND ps.skip = 0
            AND ps.deleted = 0
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $result = $statement->fetch() ?: [
        'upcoming_payments' => 0,
        'scheduled_amount' => 0,
    ];

    return [
        'id' => 'upcoming-payment-schedule',
        'name' => 'Upcoming Payment Schedule',
        'description' => 'Payments scheduled within the selected date range.',
        'url' => buildUpcomingScheduleUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
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
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildAllLoansReport(PDO $pdo, string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
{
    $sql = <<<SQL
        SELECT
            COUNT(*) AS loan_count,
            COUNT(DISTINCT b.uid) AS borrower_count,
            COALESCE(SUM(d.amount), 0) AS total_amount
        FROM disbursements d
        LEFT JOIN applications ap ON d.application_id = ap.id
        LEFT JOIN borrowers b ON ap.borrower_id = b.id
        WHERE ap.deleted = 0
            AND d.date IS NOT NULL
            AND d.date BETWEEN :startDate AND :endDate
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $result = $statement->fetch() ?: [
        'loan_count' => 0,
        'borrower_count' => 0,
        'total_amount' => 0,
    ];

    return [
        'id' => 'all-loans-report',
        'name' => 'All Loans Report',
        'description' => 'Detailed listing of loans alongside borrower contact information.',
        'url' => buildAllLoansReportUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
        'metrics' => [
            [
                'label' => 'Total Loan Amount',
                'value' => (float) $result['total_amount'],
                'formatted' => number_format((float) $result['total_amount'], 2),
            ],
            [
                'label' => 'Loan Count',
                'value' => (int) $result['loan_count'],
                'formatted' => (int) $result['loan_count'],
            ],
            [
                'label' => 'Unique Borrowers',
                'value' => (int) $result['borrower_count'],
                'formatted' => (int) $result['borrower_count'],
            ],
        ],
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildBorrowerListReport(PDO $pdo, string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
{
    $sql = <<<SQL
        WITH borrower_totals AS (
            SELECT
                b.id AS borrower_id,
                COUNT(DISTINCT d.id) AS loan_count,
                COALESCE(SUM(d.amount), 0) AS total_loan_amount
            FROM borrowers b
            INNER JOIN applications ap ON ap.borrower_id = b.id AND ap.deleted = 0
            INNER JOIN disbursements d ON d.application_id = ap.id
            WHERE d.date BETWEEN :startDate AND :endDate
            GROUP BY b.id
        )
        SELECT
            COUNT(*) AS borrower_count,
            COALESCE(SUM(total_loan_amount), 0) AS total_loan_amount,
            COALESCE(SUM(loan_count), 0) AS total_loans
        FROM borrower_totals
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $result = $statement->fetch(PDO::FETCH_ASSOC) ?: [
        'borrower_count' => 0,
        'total_loan_amount' => 0,
        'total_loans' => 0,
    ];

    return [
        'id' => 'borrower-list',
        'name' => 'Borrower List',
        'description' => 'Master borrower directory with loan totals for the selected period.',
        'url' => buildBorrowerListUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
        'metrics' => [
            [
                'label' => 'Borrowers with Loans',
                'value' => (int) $result['borrower_count'],
                'formatted' => (int) $result['borrower_count'],
            ],
            [
                'label' => 'Total Loan Amount',
                'value' => (float) $result['total_loan_amount'],
                'formatted' => number_format((float) $result['total_loan_amount'], 2),
            ],
            [
                'label' => 'Total Loans',
                'value' => (int) $result['total_loans'],
                'formatted' => (int) $result['total_loans'],
            ],
        ],
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildStaticDisbursementReport(string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
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
        'url' => buildDisbursementSummaryUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
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

function buildStaticRepaymentReport(string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
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
        'url' => buildRepaymentPerformanceUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
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

function buildStaticUpcomingScheduleReport(string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
{
    $schedules = getStaticPaymentSchedules();

    $count = 0;
    $total = 0.0;

    foreach ($schedules as $schedule) {
        if (($schedule['skip'] ?? 0) === 1 || ($schedule['deleted'] ?? 0) === 1) {
            continue;
        }

        $date = $schedule['date'];
        if ($date < $startDate || $date > $endDate) {
            continue;
        }

        $count++;
        $total += $schedule['amount'];
    }

    return [
        'id' => 'upcoming-payment-schedule',
        'name' => 'Upcoming Payment Schedule',
        'description' => 'Payments scheduled within the selected date range.',
        'url' => buildUpcomingScheduleUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
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
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildStaticAllLoansReport(string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
{
    $disbursements = getStaticDisbursements();
    $applications = getStaticApplications();
    $borrowers = getStaticBorrowers();

    $applicationIndex = [];
    foreach ($applications as $application) {
        if (!isset($application['id'])) {
            continue;
        }

        $applicationIndex[(int) $application['id']] = $application;
    }

    $borrowerIndex = [];
    foreach ($borrowers as $borrower) {
        if (!isset($borrower['id'])) {
            continue;
        }

        $borrowerIndex[(int) $borrower['id']] = $borrower;
    }

    $loanCount = 0;
    $totalAmount = 0.0;
    $borrowerKeys = [];

    foreach ($disbursements as $disbursement) {
        $date = $disbursement['date'] ?? null;
        if ($date === null || $date < $startDate || $date > $endDate) {
            continue;
        }

        $applicationId = (int) ($disbursement['application_id'] ?? 0);
        $application = $applicationIndex[$applicationId] ?? null;
        if ($application === null || ($application['deleted'] ?? 0) === 1) {
            continue;
        }

        $loanCount++;
        $totalAmount += (float) ($disbursement['amount'] ?? 0.0);

        $borrowerId = (int) ($application['borrower_id'] ?? 0);
        $borrower = $borrowerIndex[$borrowerId] ?? null;
        $borrowerKey = $borrower['uid'] ?? ($borrowerId > 0 ? (string) $borrowerId : null);
        if ($borrowerKey !== null && $borrowerKey !== '') {
            $borrowerKeys[$borrowerKey] = true;
        }
    }

    $borrowerCount = count($borrowerKeys);

    return [
        'id' => 'all-loans-report',
        'name' => 'All Loans Report',
        'description' => 'Detailed listing of loans alongside borrower contact information.',
        'url' => buildAllLoansReportUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
        'metrics' => [
            [
                'label' => 'Total Loan Amount',
                'value' => $totalAmount,
                'formatted' => number_format($totalAmount, 2),
            ],
            [
                'label' => 'Loan Count',
                'value' => $loanCount,
                'formatted' => $loanCount,
            ],
            [
                'label' => 'Unique Borrowers',
                'value' => $borrowerCount,
                'formatted' => $borrowerCount,
            ],
        ],
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

function buildStaticBorrowerListReport(string $startDate, string $endDate, ?string $startDateFilter = null, ?string $endDateFilter = null): array
{
    $summaries = getStaticBorrowerLoanSummaries($startDate, $endDate);

    $borrowerCount = count($summaries);
    $totalLoanAmount = 0.0;
    $totalLoans = 0;

    foreach ($summaries as $summary) {
        $totalLoanAmount += (float) ($summary['total_loan_amount'] ?? 0.0);
        $totalLoans += (int) ($summary['loan_count'] ?? 0);
    }

    return [
        'id' => 'borrower-list',
        'name' => 'Borrower List',
        'description' => 'Master borrower directory with loan totals for the selected period.',
        'url' => buildBorrowerListUrl($startDateFilter, $endDateFilter),
        'openInNewTab' => true,
        'metrics' => [
            [
                'label' => 'Borrowers with Loans',
                'value' => $borrowerCount,
                'formatted' => $borrowerCount,
            ],
            [
                'label' => 'Total Loan Amount',
                'value' => $totalLoanAmount,
                'formatted' => number_format($totalLoanAmount, 2),
            ],
            [
                'label' => 'Total Loans',
                'value' => $totalLoans,
                'formatted' => $totalLoans,
            ],
        ],
        'suggestedFilters' => ['startDate', 'endDate'],
    ];
}

/** Static dataset helpers are located in backend/lib/static_data.php. */
