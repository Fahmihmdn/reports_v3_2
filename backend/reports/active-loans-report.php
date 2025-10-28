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
        $rows = fetchActiveLoanDetails($pdo, $startDate, $endDate);
    } else {
        $rows = buildStaticActiveLoanDetails($startDate, $endDate);
        $errorMessage = 'Database connection unavailable.';
    }
} catch (Throwable $exception) {
    $errorMessage = 'Unexpected error while loading data.';
    $rows = buildStaticActiveLoanDetails($startDate, $endDate);
}

$loanCount = count($rows);
$totalDisbursed = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['loan_amount'], 0.0);
$totalPrincipalRepaid = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['principal_repaid'], 0.0);
$totalOutstanding = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['outstanding_principal'], 0.0);
$recentPaymentCount = count(array_filter($rows, static fn (array $row): bool => $row['last_payment_date'] !== null && $row['last_payment_date'] >= $startDate && $row['last_payment_date'] <= $endDate));
$activeStatusCount = count(array_filter($rows, static fn (array $row): bool => in_array($row['status'], ['Active', 'Pending'], true) || in_array($row['application_status'] ?? '', ['Active', 'Pending'], true)));
$averageOutstanding = $loanCount > 0 ? $totalOutstanding / $loanCount : 0.0;

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

function fetchActiveLoanDetails(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = <<<SQL
        WITH repayment_summary AS (
            SELECT
                r.disbursement_id,
                COALESCE(SUM(r.principal), 0) AS total_principal,
                COALESCE(SUM(r.amount), 0) AS total_amount,
                MAX(r.date) AS last_payment_date
            FROM repayments r
            WHERE r.deleted = 0
                AND (r.cheque_dishonour IS NULL OR r.cheque_dishonour != '1')
            GROUP BY r.disbursement_id
        )
        SELECT
            d.id AS disbursement_id,
            ap.id AS application_id,
            ap.borrower_id,
            b.uid AS borrower_uid,
            b.name AS borrower_name,
            b.hand_phone AS borrower_phone,
            b.email AS borrower_email,
            b.address1 AS borrower_address,
            d.account_number,
            d.date AS disbursement_date,
            d.amount AS loan_amount,
            d.payment_frequency_id,
            d.book_id,
            d.installment_count,
            d.status,
            ap.loan_status_id,
            rep.total_principal,
            rep.total_amount,
            rep.last_payment_date,
            GREATEST(d.amount - COALESCE(rep.total_principal, 0), 0) AS outstanding_principal
        FROM disbursements d
        LEFT JOIN applications ap ON d.application_id = ap.id
        LEFT JOIN borrowers b ON ap.borrower_id = b.id
        LEFT JOIN repayment_summary rep ON rep.disbursement_id = d.id
        WHERE ap.deleted = 0
            AND (ap.loan_status_id IS NULL OR ap.loan_status_id NOT IN ('Cancelled', 'Canceled', 'Rejected'))
            AND (
                GREATEST(d.amount - COALESCE(rep.total_principal, 0), 0) > 0
                OR (rep.last_payment_date IS NOT NULL AND rep.last_payment_date BETWEEN :startDate AND :endDate)
                OR (COALESCE(d.status, '') IN ('Active', 'Pending'))
                OR (COALESCE(ap.loan_status_id, '') IN ('Active', 'Pending'))
            )
            AND (
                d.date BETWEEN :startDate AND :endDate
                OR (rep.last_payment_date IS NOT NULL AND rep.last_payment_date BETWEEN :startDate AND :endDate)
                OR GREATEST(d.amount - COALESCE(rep.total_principal, 0), 0) > 0
            )
        ORDER BY outstanding_principal DESC, COALESCE(rep.last_payment_date, d.date) DESC
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
            'borrower_uid' => $row['borrower_uid'] ?? null,
            'borrower_name' => $row['borrower_name'] ?? 'Unknown borrower',
            'borrower_phone' => $row['borrower_phone'] ?? null,
            'borrower_email' => $row['borrower_email'] ?? null,
            'borrower_address' => $row['borrower_address'] ?? null,
            'account_number' => $row['account_number'] ?? null,
            'loan_date' => $row['disbursement_date'] ?? null,
            'loan_amount' => (float) ($row['loan_amount'] ?? 0.0),
            'loan_frequency' => $row['payment_frequency_id'] ?? null,
            'branch' => $row['book_id'] ?? null,
            'tenure' => $row['installment_count'] !== null ? (int) $row['installment_count'] : null,
            'status' => $row['status'] ?? null,
            'application_status' => $row['loan_status_id'] ?? null,
            'principal_repaid' => (float) ($row['total_principal'] ?? 0.0),
            'total_paid' => (float) ($row['total_amount'] ?? 0.0),
            'outstanding_principal' => (float) ($row['outstanding_principal'] ?? 0.0),
            'last_payment_date' => $row['last_payment_date'] ?? null,
        ];
    }

    return $results;
}

function buildStaticActiveLoanDetails(string $startDate, string $endDate): array
{
    $disbursements = getStaticDisbursements();
    $applications = getStaticApplications();
    $borrowers = getStaticBorrowers();
    $repayments = getStaticRepayments();

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

    $results = [];

    foreach ($disbursements as $disbursement) {
        $disbursementId = (int) ($disbursement['id'] ?? 0);
        $applicationId = (int) ($disbursement['application_id'] ?? 0);
        $application = $applicationIndex[$applicationId] ?? null;

        if ($application === null || ($application['deleted'] ?? 0) === 1) {
            continue;
        }

        $borrowerId = (int) ($application['borrower_id'] ?? 0);
        $borrower = $borrowerIndex[$borrowerId] ?? [];

        $loanAmount = (float) ($disbursement['amount'] ?? 0.0);
        $repaymentList = $repaymentsByDisbursement[$disbursementId] ?? [];
        $principalRepaid = 0.0;
        $totalPaid = 0.0;
        $lastPaymentDate = null;

        foreach ($repaymentList as $repayment) {
            $principalRepaid += (float) ($repayment['principal'] ?? 0.0);
            $totalPaid += (float) ($repayment['amount'] ?? 0.0);

            $datePaid = $repayment['date'] ?? null;
            if ($datePaid !== null && ($lastPaymentDate === null || $datePaid > $lastPaymentDate)) {
                $lastPaymentDate = $datePaid;
            }
        }

        $outstanding = max($loanAmount - $principalRepaid, 0.0);
        $status = $disbursement['status'] ?? null;
        $applicationStatus = $application['loan_status_id'] ?? null;

        $recentPayment = $lastPaymentDate !== null && $lastPaymentDate >= $startDate && $lastPaymentDate <= $endDate;
        $isActiveStatus = in_array($status, ['Active', 'Pending'], true) || in_array($applicationStatus, ['Active', 'Pending'], true);

        $loanDate = $disbursement['date'] ?? null;
        $withinRange = ($loanDate !== null && $loanDate >= $startDate && $loanDate <= $endDate) || $recentPayment || $outstanding > 0.0;

        if (!$recentPayment && !$isActiveStatus && $outstanding <= 0.0) {
            continue;
        }

        if (!$withinRange) {
            continue;
        }

        $results[] = [
            'disbursement_id' => $disbursementId,
            'application_id' => $applicationId,
            'borrower_id' => $borrowerId,
            'borrower_uid' => $borrower['uid'] ?? null,
            'borrower_name' => $borrower['name'] ?? 'Sample Borrower',
            'borrower_phone' => $borrower['hand_phone'] ?? null,
            'borrower_email' => $borrower['email'] ?? null,
            'borrower_address' => $borrower['address1'] ?? null,
            'account_number' => $disbursement['account_number'] ?? null,
            'loan_date' => $loanDate,
            'loan_amount' => $loanAmount,
            'loan_frequency' => $disbursement['payment_frequency_id'] ?? null,
            'branch' => $disbursement['book_id'] ?? null,
            'tenure' => $disbursement['installment_count'] ?? null,
            'status' => $status,
            'application_status' => $applicationStatus,
            'principal_repaid' => $principalRepaid,
            'total_paid' => $totalPaid,
            'outstanding_principal' => $outstanding,
            'last_payment_date' => $lastPaymentDate,
        ];
    }

    usort(
        $results,
        static fn (array $a, array $b): int => $b['outstanding_principal'] <=> $a['outstanding_principal']
    );

    return $results;
}

function formatCurrency(float $amount): string
{
    return number_format($amount, 2);
}

function formatDisplayDate(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    if ($dateTime === false) {
        return $date;
    }

    return $dateTime->format('j M Y');
}

function formatContact(?string $phone, ?string $email): string
{
    $parts = [];
    if ($phone) {
        $parts[] = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    }

    if ($email) {
        $parts[] = sprintf('<a href="mailto:%1$s">%1$s</a>', htmlspecialchars($email, ENT_QUOTES, 'UTF-8'));
    }

    return implode('<br>', $parts) ?: '—';
}

function formatStatus(?string $status): string
{
    return $status !== null && $status !== '' ? htmlspecialchars($status, ENT_QUOTES, 'UTF-8') : '—';
}

function formatLoanFrequency(?string $frequency): string
{
    return $frequency !== null && $frequency !== '' ? htmlspecialchars($frequency, ENT_QUOTES, 'UTF-8') : '—';
}

function formatBranch($branch): string
{
    if ($branch === null || $branch === '') {
        return '—';
    }

    return htmlspecialchars((string) $branch, ENT_QUOTES, 'UTF-8');
}

function daysSince(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if ($dateTime === false) {
        return '—';
    }

    $today = new DateTimeImmutable('today');
    $interval = $today->diff($dateTime);

    $days = (int) $interval->format('%r%a');

    return $days >= 0 ? sprintf('%d days ago', $days) : sprintf('In %d days', abs($days));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Loans Report</title>
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

        .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background-color: #0f172a;
            color: #f8fafc;
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .metric-card h2 {
            font-size: 0.875rem;
            margin: 0 0 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(248, 250, 252, 0.75);
        }

        .metric-card p {
            margin: 0;
            font-size: 1.5rem;
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

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 12px;
        }

        thead {
            background-color: #0f172a;
            color: #fff;
        }

        th,
        td {
            padding: 0.75rem 0.875rem;
            text-align: left;
            vertical-align: top;
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

        .contact-cell {
            font-size: 0.9rem;
        }

        tfoot td {
            font-weight: 600;
            background-color: #e2e8f0;
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
    <h1>Active Loans Report</h1>
    <p class="subtitle">Loans with outstanding balances or recent repayment activity, including borrower contact details.</p>
</header>
<main>
    <?php if ($errorMessage !== null): ?>
        <div class="error-banner"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="report-meta">
        <div class="meta-item">
            <span class="meta-label">Report period start</span>
            <span class="meta-value"><?php echo htmlspecialchars($startLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Report period end</span>
            <span class="meta-value"><?php echo htmlspecialchars($endLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Loans listed</span>
            <span class="meta-value"><?php echo number_format($loanCount); ?></span>
        </div>
    </section>

    <section class="metrics">
        <div class="metric-card">
            <h2>Outstanding Principal</h2>
            <p>$<?php echo formatCurrency($totalOutstanding); ?></p>
        </div>
        <div class="metric-card">
            <h2>Average Outstanding</h2>
            <p>$<?php echo formatCurrency($averageOutstanding); ?></p>
        </div>
        <div class="metric-card">
            <h2>Recent Payments</h2>
            <p><?php echo number_format($recentPaymentCount); ?></p>
        </div>
        <div class="metric-card">
            <h2>Active Status Loans</h2>
            <p><?php echo number_format($activeStatusCount); ?></p>
        </div>
    </section>

    <div class="actions">
        <button type="button" onclick="window.print()">Print report</button>
    </div>

    <?php if ($loanCount === 0): ?>
        <div class="empty-state">No active loans found for the selected filters.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Contact</th>
                        <th>Account #</th>
                        <th>Disbursed</th>
                        <th class="numeric">Disbursed Amount</th>
                        <th class="numeric">Principal Repaid</th>
                        <th class="numeric">Outstanding</th>
                        <th>Last Payment</th>
                        <th>Since</th>
                        <th>Frequency</th>
                        <th>Branch</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['borrower_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span><?php echo $row['borrower_uid'] ? htmlspecialchars($row['borrower_uid'], ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                            </td>
                            <td class="contact-cell"><?php echo formatContact($row['borrower_phone'], $row['borrower_email']); ?></td>
                            <td><?php echo $row['account_number'] ? htmlspecialchars($row['account_number'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td><?php echo formatDisplayDate($row['loan_date']); ?></td>
                            <td class="numeric">$<?php echo formatCurrency((float) $row['loan_amount']); ?></td>
                            <td class="numeric">$<?php echo formatCurrency((float) $row['principal_repaid']); ?></td>
                            <td class="numeric">$<?php echo formatCurrency((float) $row['outstanding_principal']); ?></td>
                            <td><?php echo formatDisplayDate($row['last_payment_date']); ?></td>
                            <td><?php echo daysSince($row['last_payment_date']); ?></td>
                            <td><?php echo formatLoanFrequency($row['loan_frequency']); ?></td>
                            <td><?php echo formatBranch($row['branch']); ?></td>
                            <td>
                                <div><?php echo formatStatus($row['status']); ?></div>
                                <?php if (($row['application_status'] ?? null) && ($row['application_status'] !== $row['status'])): ?>
                                    <small><?php echo formatStatus($row['application_status']); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Totals</td>
                        <td class="numeric">$<?php echo formatCurrency($totalDisbursed); ?></td>
                        <td class="numeric">$<?php echo formatCurrency($totalPrincipalRepaid); ?></td>
                        <td class="numeric">$<?php echo formatCurrency($totalOutstanding); ?></td>
                        <td colspan="5"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
