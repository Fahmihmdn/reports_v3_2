<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/static_data.php';

$filters = [
    'startDate' => getDateParam('startDate'),
    'endDate' => getDateParam('endDate'),
];

$startDate = $filters['startDate'] ?? '1900-01-01';
$endDate = $filters['endDate'] ?? '2100-12-31';

$errorMessage = null;

try {
    $pdo = Database::tryConnect();
    if ($pdo instanceof PDO) {
        $rows = fetchLoanDetails($pdo, $startDate, $endDate);
    } else {
        $rows = buildStaticLoanDetails($startDate, $endDate);
        $errorMessage = 'Database connection unavailable.';
    }
} catch (Throwable $exception) {
    $errorMessage = 'Unexpected error while loading data.';
    $rows = buildStaticLoanDetails($startDate, $endDate);
}

$loanCount = count($rows);
$totalAmount = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['loan_amount'], 0.0);
$totalPaidPrincipal = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['paid_principal'], 0.0);
$totalOutstandingPrincipal = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['os_principal'], 0.0);
$totalOutstandingInterest = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['os_interest'], 0.0);
$totalInterestCollected = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['interest_collected'], 0.0);
$totalProfit = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['profit'], 0.0);
$uniqueBorrowers = count(array_unique(array_filter(
    array_map(
        static fn (array $row): string => (string) ($row['borrower_uid'] ?? ($row['borrower_id'] ?? '')),
        $rows
    ),
    static fn (string $value): bool => $value !== ''
)));

$startLabel = $filters['startDate'] ? formatDisplayDate($filters['startDate']) : 'Earliest available';
$endLabel = $filters['endDate'] ? formatDisplayDate($filters['endDate']) : 'Latest available';

function getDateParam(string $key): ?string
{
    $value = $_GET[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTime ? $date->format('Y-m-d') : null;
}

function fetchLoanDetails(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = <<<SQL
        SELECT
            d.id AS disbursement_id,
            ap.id AS application_id,
            ap.borrower_id,
            b.uid,
            b.name AS borrower,
            b.gender,
            b.dob,
            b.annual_income AS income,
            b.blk AS block,
            b.street,
            b.unit AS unit_no,
            b.building,
            b.pincode AS postal,
            b.address1 AS address,
            b.email AS borrower_email,
            b.hand_phone AS borrower_phone,
            d.account_number,
            d.date AS loan_date,
            d.amount AS loan_amount,
            d.remarks,
            d.status,
            d.installment_count AS tenure,
            (
                SELECT COALESCE(SUM(principal), 0)
                FROM repayments
                WHERE disbursement_id = d.id
                    AND cheque_dishonour != '1'
                    AND deleted = 0
            ) AS paid_principal,
            d.amount - (
                SELECT COALESCE(SUM(principal), 0)
                FROM repayments
                WHERE disbursement_id = d.id
                    AND cheque_dishonour != '1'
                    AND deleted = 0
            ) AS os_principal,
            (
                SELECT COALESCE(SUM(interest), 0)
                FROM payment_schedule
                WHERE disbursement_id = d.id
                    AND deleted = 0
            ) - (
                SELECT COALESCE(SUM(interest), 0)
                FROM repayments
                WHERE disbursement_id = d.id
                    AND cheque_dishonour != '1'
                    AND deleted = 0
            ) AS os_interest,
            (
                SELECT COALESCE(SUM(r.amount), 0)
                FROM repayments r
                WHERE r.disbursement_id = d.id
                    AND r.cheque_dishonour != '1'
                    AND r.deleted = 0
            ) - d.amount AS profit,
            (
                SELECT COALESCE(SUM(interest), 0)
                FROM repayments
                WHERE disbursement_id = d.id
                    AND cheque_dishonour != '1'
                    AND deleted = 0
            ) AS interest_collected,
            (
                SELECT COALESCE(SUM(legal_fee + acceptance_fee + contract_variation_fee + cheque_dishonoured_fee + termination_fee + renewal_fee + late_fee), 0)
                FROM repayments
                WHERE disbursement_id = d.id
                    AND cheque_dishonour != '1'
                    AND deleted = 0
            ) AS permit_fee_collected,
            (
                SELECT date
                FROM repayments
                WHERE disbursement_id = d.id
                    AND deleted = 0
                ORDER BY date DESC
                LIMIT 1
            ) AS last_paid_date
        FROM disbursements d
        LEFT JOIN applications ap ON d.application_id = ap.id
        LEFT JOIN borrowers b ON ap.borrower_id = b.id
        WHERE ap.deleted = 0
            AND d.date BETWEEN :startDate AND :endDate
        ORDER BY d.date DESC, d.id DESC
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $results = [];

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'disbursement_id' => (int) ($row['disbursement_id'] ?? 0),
            'application_id' => $row['application_id'] !== null ? (int) $row['application_id'] : null,
            'borrower_id' => $row['borrower_id'] !== null ? (int) $row['borrower_id'] : null,
            'borrower_uid' => $row['uid'] ?? null,
            'borrower_name' => $row['borrower'] ?? 'Unknown borrower',
            'gender' => $row['gender'] ?? null,
            'dob' => $row['dob'] ?? null,
            'annual_income' => (float) ($row['income'] ?? 0.0),
            'block' => $row['block'] ?? null,
            'street' => $row['street'] ?? null,
            'unit_no' => $row['unit_no'] ?? null,
            'building' => $row['building'] ?? null,
            'postal' => $row['postal'] ?? null,
            'address' => $row['address'] ?? null,
            'borrower_email' => $row['borrower_email'] ?? null,
            'borrower_phone' => $row['borrower_phone'] ?? null,
            'account_number' => $row['account_number'] ?? null,
            'loan_date' => $row['loan_date'] ?? null,
            'loan_amount' => (float) ($row['loan_amount'] ?? 0.0),
            'remarks' => $row['remarks'] ?? null,
            'status' => $row['status'] ?? null,
            'tenure' => $row['tenure'] !== null ? (int) $row['tenure'] : null,
            'paid_principal' => (float) ($row['paid_principal'] ?? 0.0),
            'os_principal' => (float) ($row['os_principal'] ?? 0.0),
            'os_interest' => (float) ($row['os_interest'] ?? 0.0),
            'profit' => (float) ($row['profit'] ?? 0.0),
            'interest_collected' => (float) ($row['interest_collected'] ?? 0.0),
            'permit_fee_collected' => (float) ($row['permit_fee_collected'] ?? 0.0),
            'last_paid_date' => $row['last_paid_date'] ?? null,
        ];
    }

    return $results;
}

function buildStaticLoanDetails(string $startDate, string $endDate): array
{
    $disbursements = getStaticDisbursements();
    $applications = getStaticApplications();
    $borrowers = getStaticBorrowers();
    $repayments = getStaticRepayments();
    $schedules = getStaticPaymentSchedules();

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

    $repaymentsByDisbursement = [];
    foreach ($repayments as $repayment) {
        if (($repayment['deleted'] ?? 0) === 1 || ($repayment['cheque_dishonour'] ?? '0') === '1') {
            continue;
        }

        $disbursementId = (int) ($repayment['disbursement_id'] ?? 0);
        if ($disbursementId === 0) {
            continue;
        }

        if (!isset($repaymentsByDisbursement[$disbursementId])) {
            $repaymentsByDisbursement[$disbursementId] = [];
        }

        $repaymentsByDisbursement[$disbursementId][] = $repayment;
    }

    $scheduleInterestByDisbursement = [];
    foreach ($schedules as $schedule) {
        if (($schedule['deleted'] ?? 0) === 1) {
            continue;
        }

        $disbursementId = (int) ($schedule['disbursement_id'] ?? 0);
        if ($disbursementId === 0) {
            continue;
        }

        if (!isset($scheduleInterestByDisbursement[$disbursementId])) {
            $scheduleInterestByDisbursement[$disbursementId] = 0.0;
        }

        $scheduleInterestByDisbursement[$disbursementId] += (float) ($schedule['interest'] ?? 0.0);
    }

    $results = [];

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

        $borrowerId = (int) ($application['borrower_id'] ?? 0);
        $borrower = $borrowerIndex[$borrowerId] ?? [];

        $disbursementId = (int) ($disbursement['id'] ?? 0);
        $repaymentList = $repaymentsByDisbursement[$disbursementId] ?? [];

        $paidPrincipal = 0.0;
        $interestCollected = 0.0;
        $totalRepayments = 0.0;
        $permitFees = 0.0;
        $lastPaidDate = null;

        foreach ($repaymentList as $repayment) {
            $paidPrincipal += (float) ($repayment['principal'] ?? 0.0);
            $interestCollected += (float) ($repayment['interest'] ?? 0.0);
            $totalRepayments += (float) ($repayment['amount'] ?? 0.0);
            $permitFees += (float) ($repayment['legal_fee'] ?? 0.0)
                + (float) ($repayment['acceptance_fee'] ?? 0.0)
                + (float) ($repayment['contract_variation_fee'] ?? 0.0)
                + (float) ($repayment['cheque_dishonoured_fee'] ?? 0.0)
                + (float) ($repayment['termination_fee'] ?? 0.0)
                + (float) ($repayment['renewal_fee'] ?? 0.0)
                + (float) ($repayment['late_fee'] ?? 0.0);

            $datePaid = $repayment['date'] ?? null;
            if ($datePaid !== null && ($lastPaidDate === null || $datePaid > $lastPaidDate)) {
                $lastPaidDate = $datePaid;
            }
        }

        $scheduledInterest = $scheduleInterestByDisbursement[$disbursementId] ?? 0.0;
        $loanAmount = (float) ($disbursement['amount'] ?? 0.0);
        $outstandingPrincipal = $loanAmount - $paidPrincipal;
        $outstandingInterest = $scheduledInterest - $interestCollected;
        $profit = $totalRepayments - $loanAmount;

        $results[] = [
            'disbursement_id' => $disbursementId,
            'application_id' => $applicationId,
            'borrower_id' => $borrowerId,
            'borrower_uid' => $borrower['uid'] ?? null,
            'borrower_name' => $borrower['name'] ?? 'Unknown borrower',
            'gender' => $borrower['gender'] ?? null,
            'dob' => $borrower['dob'] ?? null,
            'annual_income' => (float) ($borrower['annual_income'] ?? 0.0),
            'block' => $borrower['blk'] ?? null,
            'street' => $borrower['street'] ?? null,
            'unit_no' => $borrower['unit'] ?? null,
            'building' => $borrower['building'] ?? null,
            'postal' => $borrower['pincode'] ?? null,
            'address' => $borrower['address1'] ?? null,
            'borrower_email' => $borrower['email'] ?? null,
            'borrower_phone' => $borrower['hand_phone'] ?? null,
            'account_number' => $disbursement['account_number'] ?? ($application['account_number'] ?? null),
            'loan_date' => $date,
            'loan_amount' => $loanAmount,
            'remarks' => $disbursement['remarks'] ?? null,
            'status' => $disbursement['status'] ?? null,
            'tenure' => $disbursement['installment_count'] ?? null,
            'paid_principal' => $paidPrincipal,
            'os_principal' => $outstandingPrincipal,
            'os_interest' => $outstandingInterest,
            'profit' => $profit,
            'interest_collected' => $interestCollected,
            'permit_fee_collected' => $permitFees,
            'last_paid_date' => $lastPaidDate,
        ];
    }

    usort(
        $results,
        static function (array $a, array $b): int {
            if ($a['loan_date'] === $b['loan_date']) {
                return $b['disbursement_id'] <=> $a['disbursement_id'];
            }

            return strcmp((string) $b['loan_date'], (string) $a['loan_date']);
        }
    );

    return $results;
}

function formatCurrency(float $amount): string
{
    return number_format($amount, 2);
}

function formatFullAddress(array $row): string
{
    $parts = [];

    $block = trim((string) ($row['block'] ?? ''));
    if ($block !== '') {
        $parts[] = 'Blk ' . $block;
    }

    $street = trim((string) ($row['street'] ?? ''));
    if ($street !== '') {
        $parts[] = $street;
    }

    $unit = trim((string) ($row['unit_no'] ?? ''));
    if ($unit !== '') {
        $parts[] = str_starts_with($unit, '#') ? $unit : '#' . $unit;
    }

    $building = trim((string) ($row['building'] ?? ''));
    if ($building !== '') {
        $parts[] = $building;
    }

    $address = trim((string) ($row['address'] ?? ''));
    if ($address !== '') {
        $parts[] = $address;
    }

    $postal = trim((string) ($row['postal'] ?? ''));
    if ($postal !== '') {
        $parts[] = 'Postal ' . $postal;
    }

    return $parts === [] ? '—' : implode(', ', $parts);
}

function formatNullableDate(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '—';
    }

    return formatDisplayDate($date);
}

function formatDisplayDate(string $date): string
{
    $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    if ($dateTime === false) {
        return $date;
    }

    return $dateTime->format('j M Y');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Loans Report</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Inter", "Segoe UI", Roboto, -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif;
        }

        body {
            margin: 0;
            padding: 2.5rem 1.5rem 4rem;
            background-color: #f5f6fa;
            color: #1a1a1a;
        }

        header {
            max-width: 1200px;
            margin: 0 auto 2rem;
        }

        h1 {
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        p.subtitle {
            margin: 0;
            color: #4a5568;
        }

        main {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            padding: 2rem;
        }

        .report-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .preview-card {
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            background: linear-gradient(145deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.25);
        }

        .preview-card h2 {
            margin: 0 0 0.25rem;
            font-size: 0.875rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .preview-card p {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .report-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem 2rem;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            flex: 1 1 200px;
        }

        .meta-label {
            display: block;
            font-size: 0.875rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .meta-value {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1.5rem;
        }

        .actions button {
            background-color: #2563eb;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 999px;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .actions button:hover,
        .actions button:focus {
            background-color: #1d4ed8;
            transform: translateY(-1px);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 12px;
            min-width: 1200px;
        }

        thead {
            background-color: #0f172a;
            color: #fff;
        }

        th,
        td {
            padding: 0.875rem 1rem;
            text-align: left;
        }

        tbody tr:nth-child(odd) {
            background-color: #f8fafc;
        }

        tbody tr:hover {
            background-color: #eff6ff;
        }

        .numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .text-nowrap {
            white-space: nowrap;
        }

        .muted {
            color: #64748b;
            font-size: 0.85rem;
        }

        .cell-primary {
            font-weight: 600;
        }

        .cell-secondary {
            margin-top: 0.25rem;
            color: #64748b;
            font-size: 0.85rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #4a5568;
        }

        .error-banner {
            background-color: #fee2e2;
            color: #b91c1c;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        @media print {
            body {
                background: #fff;
                padding: 1rem;
            }

            main {
                box-shadow: none;
                padding: 0;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
<header>
    <h1>All Loans Report</h1>
    <p class="subtitle">Comprehensive view of loans with borrower contact information.</p>
</header>
<main>
    <?php if ($errorMessage !== null): ?>
        <div class="error-banner" role="alert">
            <?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?> Showing sample data instead.
        </div>
    <?php endif; ?>

    <section class="report-preview" aria-label="Report highlights">
        <article class="preview-card">
            <h2>Total loan amount</h2>
            <p>$<?php echo formatCurrency($totalAmount); ?></p>
        </article>
        <article class="preview-card">
            <h2>Principal repaid</h2>
            <p>$<?php echo formatCurrency($totalPaidPrincipal); ?></p>
        </article>
        <article class="preview-card">
            <h2>Outstanding principal</h2>
            <p>$<?php echo formatCurrency($totalOutstandingPrincipal); ?></p>
        </article>
        <article class="preview-card">
            <h2>Interest collected</h2>
            <p>$<?php echo formatCurrency($totalInterestCollected); ?></p>
        </article>
    </section>

    <section class="report-meta" aria-label="Report filters">
        <div class="meta-item">
            <span class="meta-label">Start date</span>
            <span class="meta-value"><?php echo htmlspecialchars($startLabel, ENT_QUOTES); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">End date</span>
            <span class="meta-value"><?php echo htmlspecialchars($endLabel, ENT_QUOTES); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Loans</span>
            <span class="meta-value"><?php echo number_format($loanCount); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Unique borrowers</span>
            <span class="meta-value"><?php echo number_format($uniqueBorrowers); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Outstanding interest</span>
            <span class="meta-value">$<?php echo formatCurrency($totalOutstandingInterest); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Total profit</span>
            <span class="meta-value">$<?php echo formatCurrency($totalProfit); ?></span>
        </div>
    </section>

    <div class="actions">
        <button type="button" id="export-pdf">Export to PDF</button>
    </div>

    <?php if (count($rows) === 0): ?>
        <div class="empty-state">
            <p>No loans found for the selected period.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                <tr>
                    <th scope="col">Borrower</th>
                    <th scope="col">Contact &amp; address</th>
                    <th scope="col">Loan details</th>
                    <th scope="col" class="numeric">Loan amount</th>
                    <th scope="col" class="numeric">Principal</th>
                    <th scope="col" class="numeric">Interest</th>
                    <th scope="col" class="numeric">Fees &amp; profit</th>
                    <th scope="col" class="text-nowrap">Last payment</th>
                    <th scope="col">Remarks</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <div class="cell-primary"><?php echo htmlspecialchars($row['borrower_name'], ENT_QUOTES); ?></div>
                            <div class="cell-secondary">
                                <?php if (!empty($row['borrower_uid'])): ?>
                                    UID: <?php echo htmlspecialchars((string) $row['borrower_uid'], ENT_QUOTES); ?>
                                <?php else: ?>
                                    <span class="muted">UID unavailable</span>
                                <?php endif; ?>
                            </div>
                            <div class="cell-secondary">
                                <?php
                                $demographicPieces = [];
                                if (isset($row['gender']) && $row['gender'] !== '') {
                                    $demographicPieces[] = htmlspecialchars((string) $row['gender'], ENT_QUOTES);
                                }
                                $dobLabel = formatNullableDate($row['dob']);
                                if ($dobLabel !== '—') {
                                    $demographicPieces[] = htmlspecialchars($dobLabel, ENT_QUOTES);
                                }

                                if ($demographicPieces === []) {
                                    echo "<span class=\"muted\">No demographic data</span>";
                                } else {
                                    echo implode(' • ', $demographicPieces);
                                }
                                ?>
                            </div>
                            <?php if (($row['annual_income'] ?? 0.0) > 0): ?>
                                <div class="cell-secondary">Annual income: $<?php echo formatCurrency((float) $row['annual_income']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="cell-secondary">
                                <?php if (!empty($row['borrower_phone'])): ?>
                                    <?php echo htmlspecialchars((string) $row['borrower_phone'], ENT_QUOTES); ?>
                                <?php else: ?>
                                    <span class="muted">No phone on file</span>
                                <?php endif; ?>
                            </div>
                            <div class="cell-secondary">
                                <?php if (!empty($row['borrower_email'])): ?>
                                    <?php echo htmlspecialchars((string) $row['borrower_email'], ENT_QUOTES); ?>
                                <?php else: ?>
                                    <span class="muted">No email on file</span>
                                <?php endif; ?>
                            </div>
                            <div class="cell-secondary">Address: <?php echo htmlspecialchars(formatFullAddress($row), ENT_QUOTES); ?></div>
                        </td>
                        <td>
                            <div class="cell-secondary">Disbursement #<?php echo htmlspecialchars((string) ($row['disbursement_id'] ?? '—'), ENT_QUOTES); ?></div>
                            <div class="cell-secondary">Account: <?php echo htmlspecialchars((string) ($row['account_number'] ?? '—'), ENT_QUOTES); ?></div>
                            <div class="cell-secondary">Loan date: <?php echo htmlspecialchars(formatNullableDate($row['loan_date']), ENT_QUOTES); ?></div>
                            <div class="cell-secondary">Tenure: <?php echo htmlspecialchars($row['tenure'] !== null ? (string) $row['tenure'] . ' instalments' : '—', ENT_QUOTES); ?></div>
                            <div class="cell-secondary">Status: <?php echo htmlspecialchars($row['status'] ?? '—', ENT_QUOTES); ?></div>
                        </td>
                        <td class="numeric">$<?php echo formatCurrency((float) $row['loan_amount']); ?></td>
                        <td class="numeric">
                            <div>Paid: $<?php echo formatCurrency((float) $row['paid_principal']); ?></div>
                            <div>Outstanding: $<?php echo formatCurrency((float) $row['os_principal']); ?></div>
                        </td>
                        <td class="numeric">
                            <div>Outstanding: $<?php echo formatCurrency((float) $row['os_interest']); ?></div>
                            <div>Collected: $<?php echo formatCurrency((float) $row['interest_collected']); ?></div>
                        </td>
                        <td class="numeric">
                            <div>Permit fees: $<?php echo formatCurrency((float) $row['permit_fee_collected']); ?></div>
                            <div>Profit: $<?php echo formatCurrency((float) $row['profit']); ?></div>
                        </td>
                        <td class="text-nowrap"><?php echo htmlspecialchars(formatNullableDate($row['last_paid_date']), ENT_QUOTES); ?></td>
                        <td><?php echo htmlspecialchars($row['remarks'] !== null && $row['remarks'] !== '' ? $row['remarks'] : '—', ENT_QUOTES); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
<script>
    document.getElementById('export-pdf')?.addEventListener('click', () => {
        window.print();
    });
</script>
</body>
</html>
