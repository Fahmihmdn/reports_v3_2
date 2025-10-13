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
        $rows = fetchRepaymentSummary($pdo, $startDate, $endDate);
    } else {
        $rows = buildStaticRepaymentSummary($startDate, $endDate);
        $errorMessage = 'Database connection unavailable.';
    }
} catch (Throwable $exception) {
    $errorMessage = 'Unexpected error while loading data.';
    $rows = buildStaticRepaymentSummary($startDate, $endDate);
}

$totalPrincipal = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['principal'], 0.0);
$totalInterest = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['interest'], 0.0);
$totalAmount = array_reduce($rows, static fn (float $carry, array $row): float => $carry + (float) $row['amount'], 0.0);

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

function fetchRepaymentSummary(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = <<<SQL
        SELECT
            r.date AS repayment_date,
            COALESCE(SUM(r.principal), 0) AS total_principal,
            COALESCE(SUM(r.interest), 0) AS total_interest,
            COALESCE(SUM(r.amount), 0) AS total_amount
        FROM repayments r
        WHERE r.deleted = 0
          AND r.date IS NOT NULL
          AND r.date BETWEEN :startDate AND :endDate
        GROUP BY r.date
        ORDER BY r.date ASC
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $results = [];

    while ($row = $statement->fetch()) {
        $results[] = [
            'date' => $row['repayment_date'],
            'principal' => (float) $row['total_principal'],
            'interest' => (float) $row['total_interest'],
            'amount' => (float) $row['total_amount'],
        ];
    }

    return $results;
}

function buildStaticRepaymentSummary(string $startDate, string $endDate): array
{
    $staticRepayments = getStaticRepayments();
    $grouped = [];

    foreach ($staticRepayments as $repayment) {
        $date = $repayment['date'] ?? null;
        if ($date === null) {
            continue;
        }

        if (($repayment['deleted'] ?? 0) === 1) {
            continue;
        }

        if ($date < $startDate || $date > $endDate) {
            continue;
        }

        if (!isset($grouped[$date])) {
            $grouped[$date] = [
                'principal' => 0.0,
                'interest' => 0.0,
                'amount' => 0.0,
            ];
        }

        $grouped[$date]['principal'] += (float) ($repayment['principal'] ?? 0.0);
        $grouped[$date]['interest'] += (float) ($repayment['interest'] ?? 0.0);
        $grouped[$date]['amount'] += (float) ($repayment['amount'] ?? 0.0);
    }

    ksort($grouped);

    $results = [];
    foreach ($grouped as $date => $totals) {
        $results[] = [
            'date' => $date,
            'principal' => (float) $totals['principal'],
            'interest' => (float) $totals['interest'],
            'amount' => (float) $totals['amount'],
        ];
    }

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
    <title>Repayment Performance Summary</title>
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
            max-width: 960px;
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
            max-width: 960px;
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
    <h1>Repayment Performance Summary</h1>
    <p class="subtitle">Daily aggregates of borrower repayments within the selected period.</p>
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
            <span class="meta-label">Total principal repaid</span>
            <span class="meta-value">$<?php echo formatCurrency($totalPrincipal); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Total interest repaid</span>
            <span class="meta-value">$<?php echo formatCurrency($totalInterest); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Total repaid</span>
            <span class="meta-value">$<?php echo formatCurrency($totalAmount); ?></span>
        </div>
    </section>

    <div class="actions">
        <button type="button" id="export-pdf">Export to PDF</button>
    </div>

    <?php if (count($rows) === 0): ?>
        <div class="empty-state">
            <p>No repayments found for the selected period.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th scope="col">Repayment date</th>
                <th scope="col" class="numeric">Principal (USD)</th>
                <th scope="col" class="numeric">Interest (USD)</th>
                <th scope="col" class="numeric">Total repaid (USD)</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars(formatDisplayDate($row['date']), ENT_QUOTES); ?></td>
                    <td class="numeric">$<?php echo formatCurrency((float) $row['principal']); ?></td>
                    <td class="numeric">$<?php echo formatCurrency((float) $row['interest']); ?></td>
                    <td class="numeric">$<?php echo formatCurrency((float) $row['amount']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <td>Total</td>
                <td class="numeric">$<?php echo formatCurrency($totalPrincipal); ?></td>
                <td class="numeric">$<?php echo formatCurrency($totalInterest); ?></td>
                <td class="numeric">$<?php echo formatCurrency($totalAmount); ?></td>
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
