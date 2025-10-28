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
        $rows = fetchUpcomingScheduleDetails($pdo, $startDate, $endDate);
    } else {
        $rows = buildStaticUpcomingScheduleDetails($startDate, $endDate);
        $errorMessage = 'Database connection unavailable.';
    }
} catch (Throwable $exception) {
    $errorMessage = 'Unexpected error while loading data.';
    $rows = buildStaticUpcomingScheduleDetails($startDate, $endDate);
}

$totalScheduled = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['amount'], 0.0);
$totalPrincipal = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['principal'], 0.0);
$totalInterest = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['interest'], 0.0);
$totalLateFee = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['late_fee'], 0.0);
$totalLateInterest = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['late_interest'], 0.0);

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

function fetchUpcomingScheduleDetails(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = <<<SQL
        SELECT
            ps.date,
            ps.amount,
            ps.principal,
            ps.interest,
            ps.late_fee,
            ps.late_interest,
            ps.legal_fee,
            ps.renewal_fee,
            ps.contract_variation_fee,
            ps.cheque_dishonour_fee,
            ps.termination_fee,
            ps.google_calendar_url,
            d.account_number,
            d.amount AS loan_amount,
            b.name AS borrower,
            b.uid AS borrower_uid,
            b.hand_phone AS borrower_phone,
            b.email AS borrower_email
        FROM payment_schedule ps
        LEFT JOIN disbursements d ON ps.disbursement_id = d.id
        LEFT JOIN applications ap ON ps.application_id = ap.id
        LEFT JOIN borrowers b ON ap.borrower_id = b.id
        WHERE ps.skip = 0
            AND ps.deleted = 0
            AND ps.date IS NOT NULL
            AND ps.date > CURRENT_DATE()
            AND ps.date BETWEEN :startDate AND :endDate
        ORDER BY ps.date ASC, ps.id ASC
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $results = [];

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'date' => $row['date'],
            'amount' => (float) ($row['amount'] ?? 0.0),
            'principal' => (float) ($row['principal'] ?? 0.0),
            'interest' => (float) ($row['interest'] ?? 0.0),
            'late_fee' => (float) ($row['late_fee'] ?? 0.0),
            'late_interest' => (float) ($row['late_interest'] ?? 0.0),
            'legal_fee' => (float) ($row['legal_fee'] ?? 0.0),
            'renewal_fee' => (float) ($row['renewal_fee'] ?? 0.0),
            'contract_variation_fee' => (float) ($row['contract_variation_fee'] ?? 0.0),
            'cheque_dishonour_fee' => (float) ($row['cheque_dishonour_fee'] ?? 0.0),
            'termination_fee' => (float) ($row['termination_fee'] ?? 0.0),
            'google_calendar_url' => $row['google_calendar_url'] ?? null,
            'account_number' => $row['account_number'] ?? null,
            'loan_amount' => (float) ($row['loan_amount'] ?? 0.0),
            'borrower' => $row['borrower'] ?? '—',
            'borrower_uid' => $row['borrower_uid'] ?? null,
            'borrower_phone' => $row['borrower_phone'] ?? null,
            'borrower_email' => $row['borrower_email'] ?? null,
        ];
    }

    return $results;
}

function buildStaticUpcomingScheduleDetails(string $startDate, string $endDate): array
{
    $schedules = getStaticPaymentSchedules();
    $disbursements = getStaticDisbursements();
    $applications = getStaticApplications();
    $borrowers = getStaticBorrowers();

    $disbursementIndex = [];
    foreach ($disbursements as $disbursement) {
        if (!isset($disbursement['id'])) {
            continue;
        }
        $disbursementIndex[(int) $disbursement['id']] = $disbursement;
    }

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

    $today = (new DateTimeImmutable('now'))->format('Y-m-d');

    $results = [];

    foreach ($schedules as $schedule) {
        if (($schedule['skip'] ?? 0) === 1 || ($schedule['deleted'] ?? 0) === 1) {
            continue;
        }

        $date = $schedule['date'] ?? null;
        if ($date === null || $date <= $today || $date < $startDate || $date > $endDate) {
            continue;
        }

        $disbursement = $disbursementIndex[(int) ($schedule['disbursement_id'] ?? 0)] ?? [];
        $applicationId = (int) ($disbursement['application_id'] ?? ($schedule['application_id'] ?? 0));
        $application = $applicationIndex[$applicationId] ?? [];
        $borrower = $borrowerIndex[(int) ($application['borrower_id'] ?? 0)] ?? [];

        $results[] = [
            'date' => $date,
            'amount' => (float) ($schedule['amount'] ?? 0.0),
            'principal' => (float) ($schedule['principal'] ?? 0.0),
            'interest' => (float) ($schedule['interest'] ?? 0.0),
            'late_fee' => (float) ($schedule['late_fee'] ?? 0.0),
            'late_interest' => (float) ($schedule['late_interest'] ?? 0.0),
            'legal_fee' => (float) ($schedule['legal_fee'] ?? 0.0),
            'renewal_fee' => (float) ($schedule['renewal_fee'] ?? 0.0),
            'contract_variation_fee' => (float) ($schedule['contract_variation_fee'] ?? 0.0),
            'cheque_dishonour_fee' => (float) ($schedule['cheque_dishonour_fee'] ?? 0.0),
            'termination_fee' => (float) ($schedule['termination_fee'] ?? 0.0),
            'google_calendar_url' => $schedule['google_calendar_url'] ?? null,
            'account_number' => $disbursement['account_number'] ?? null,
            'loan_amount' => (float) ($disbursement['amount'] ?? 0.0),
            'borrower' => $borrower['name'] ?? 'Sample Borrower',
            'borrower_uid' => $borrower['uid'] ?? null,
            'borrower_phone' => $borrower['hand_phone'] ?? null,
            'borrower_email' => $borrower['email'] ?? null,
        ];
    }

    usort(
        $results,
        static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date'])
    );

    return $results;
}

function formatCurrency(float $amount): string
{
    return number_format($amount, 2);
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
    <title>Upcoming Payment Schedule</title>
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

        .contact-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            color: #1e293b;
        }

        .contact-details span {
            font-size: 0.875rem;
        }

        .calendar-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .calendar-link:hover,
        .calendar-link:focus {
            text-decoration: underline;
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
    <h1>Upcoming Payment Schedule</h1>
    <p class="subtitle">Detailed list of scheduled payments that fall after today within the selected filters.</p>
</header>
<main>
    <?php if ($errorMessage !== null): ?>
        <div class="error-banner" role="alert">
            <?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?> Showing sample data instead.
        </div>
    <?php endif; ?>

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
            <span class="meta-label">Upcoming payments</span>
            <span class="meta-value"><?php echo count($rows); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Scheduled amount</span>
            <span class="meta-value">$<?php echo formatCurrency($totalScheduled); ?></span>
        </div>
    </section>

    <div class="actions">
        <button type="button" id="export-pdf">Export to PDF</button>
    </div>

    <?php if (count($rows) === 0): ?>
        <div class="empty-state">
            <p>No upcoming payments found for the selected filters.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th scope="col">Payment date</th>
                <th scope="col">Borrower</th>
                <th scope="col">Contact</th>
                <th scope="col">Loan account</th>
                <th scope="col" class="numeric">Scheduled</th>
                <th scope="col" class="numeric">Principal</th>
                <th scope="col" class="numeric">Interest</th>
                <th scope="col" class="numeric">Late fee</th>
                <th scope="col" class="numeric">Late interest</th>
                <th scope="col">Calendar</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars(formatDisplayDate($row['date']), ENT_QUOTES); ?></td>
                    <td>
                        <div><?php echo htmlspecialchars($row['borrower'], ENT_QUOTES); ?></div>
                        <?php if (!empty($row['borrower_uid'])): ?>
                            <div style="color: #64748b; font-size: 0.875rem;">UID: <?php echo htmlspecialchars($row['borrower_uid'], ENT_QUOTES); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="contact-details">
                            <?php if (!empty($row['borrower_phone'])): ?>
                                <span>Phone: <?php echo htmlspecialchars($row['borrower_phone'], ENT_QUOTES); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['borrower_email'])): ?>
                                <span>Email: <?php echo htmlspecialchars($row['borrower_email'], ENT_QUOTES); ?></span>
                            <?php endif; ?>
                            <?php if (empty($row['borrower_phone']) && empty($row['borrower_email'])): ?>
                                <span>—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($row['account_number'] ?? '—', ENT_QUOTES); ?></td>
                    <td class="numeric">$<?php echo formatCurrency((float) $row['amount']); ?></td>
                    <td class="numeric">$<?php echo formatCurrency((float) $row['principal']); ?></td>
                    <td class="numeric">$<?php echo formatCurrency((float) $row['interest']); ?></td>
                    <td class="numeric">$<?php echo formatCurrency((float) $row['late_fee']); ?></td>
                    <td class="numeric">$<?php echo formatCurrency((float) $row['late_interest']); ?></td>
                    <td>
                        <?php if (!empty($row['google_calendar_url'])): ?>
                            <a class="calendar-link" href="<?php echo htmlspecialchars($row['google_calendar_url'], ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer">Open</a>
                        <?php else: ?>
                            <span>—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="4">Totals</td>
                <td class="numeric">$<?php echo formatCurrency($totalScheduled); ?></td>
                <td class="numeric">$<?php echo formatCurrency($totalPrincipal); ?></td>
                <td class="numeric">$<?php echo formatCurrency($totalInterest); ?></td>
                <td class="numeric">$<?php echo formatCurrency($totalLateFee); ?></td>
                <td class="numeric">$<?php echo formatCurrency($totalLateInterest); ?></td>
                <td></td>
            </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</main>
<script>
    document.getElementById('export-pdf')?.addEventListener('click', () => {
        window.print();
    });
</script>
</body>
</html>
