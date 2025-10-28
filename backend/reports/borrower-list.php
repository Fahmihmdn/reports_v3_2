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
        $rows = fetchBorrowerList($pdo, $startDate, $endDate);
    } else {
        $rows = getStaticBorrowerLoanSummaries($startDate, $endDate);
        $errorMessage = 'Database connection unavailable.';
    }
} catch (Throwable $exception) {
    $errorMessage = 'Unexpected error while loading data.';
    $rows = getStaticBorrowerLoanSummaries($startDate, $endDate);
}

$totalBorrowers = count($rows);
$totalLoanAmount = array_reduce(
    $rows,
    static fn (float $carry, array $row): float => $carry + (float) ($row['total_loan_amount'] ?? 0.0),
    0.0
);
$totalLoans = array_reduce(
    $rows,
    static fn (int $carry, array $row): int => $carry + (int) ($row['loan_count'] ?? 0),
    0
);
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

function fetchBorrowerList(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = <<<SQL
        SELECT
            b.id AS borrower_id,
            b.uid,
            b.name,
            b.gender,
            b.dob,
            b.annual_income,
            b.blk,
            b.street,
            b.unit,
            b.building,
            b.pincode,
            b.address1,
            b.email,
            b.hand_phone,
            COUNT(DISTINCT d.id) AS loan_count,
            COALESCE(SUM(d.amount), 0) AS total_loan_amount,
            MIN(d.date) AS first_loan_date,
            MAX(d.date) AS last_loan_date
        FROM borrowers b
        INNER JOIN applications ap ON ap.borrower_id = b.id AND ap.deleted = 0
        INNER JOIN disbursements d ON d.application_id = ap.id
        WHERE d.date BETWEEN :startDate AND :endDate
        GROUP BY
            b.id,
            b.uid,
            b.name,
            b.gender,
            b.dob,
            b.annual_income,
            b.blk,
            b.street,
            b.unit,
            b.building,
            b.pincode,
            b.address1,
            b.email,
            b.hand_phone
        ORDER BY b.name ASC, b.id ASC
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);

    $results = [];

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'borrower_id' => (int) $row['borrower_id'],
            'uid' => $row['uid'] ?? null,
            'name' => $row['name'] ?? null,
            'gender' => $row['gender'] ?? null,
            'dob' => $row['dob'] ?? null,
            'annual_income' => $row['annual_income'] !== null ? (float) $row['annual_income'] : null,
            'blk' => $row['blk'] ?? null,
            'street' => $row['street'] ?? null,
            'unit' => $row['unit'] ?? null,
            'building' => $row['building'] ?? null,
            'pincode' => $row['pincode'] ?? null,
            'address1' => $row['address1'] ?? null,
            'email' => $row['email'] ?? null,
            'hand_phone' => $row['hand_phone'] ?? null,
            'loan_count' => (int) $row['loan_count'],
            'total_loan_amount' => (float) $row['total_loan_amount'],
            'first_loan_date' => $row['first_loan_date'] ?? null,
            'last_loan_date' => $row['last_loan_date'] ?? null,
        ];
    }

    return $results;
}

function formatCurrency(?float $amount): string
{
    if ($amount === null) {
        return '—';
    }

    return number_format($amount, 2);
}

function formatNumber(?int $value): string
{
    if ($value === null) {
        return '—';
    }

    return number_format($value);
}

function formatText(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatDateValue(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return htmlspecialchars(formatDisplayDate($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    <title>Borrower List</title>
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
            max-width: 1100px;
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
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            padding: 2rem;
        }

        .report-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .preview-card {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: inset 0 0 0 1px #e2e8f0;
        }

        .preview-card h2 {
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            margin: 0 0 0.75rem;
        }

        .preview-card p {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .report-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .meta-label {
            font-size: 0.8rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
        }

        .meta-value {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.75rem;
        }

        .actions button {
            border: none;
            border-radius: 8px;
            padding: 0.65rem 1.25rem;
            cursor: pointer;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
        }

        .actions button:focus {
            outline: 2px solid #1d4ed8;
            outline-offset: 2px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }

        thead {
            background-color: #1e293b;
            color: #ffffff;
        }

        th,
        td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        th {
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
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
    <h1>Borrower List</h1>
    <p class="subtitle">Master borrower directory with loan totals for the selected period.</p>
</header>
<main>
    <?php if ($errorMessage !== null): ?>
        <div class="error-banner" role="alert">
            <?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?> Showing sample data instead.
        </div>
    <?php endif; ?>

    <section class="report-preview" aria-label="Report highlights">
        <article class="preview-card">
            <h2>Borrowers with loans</h2>
            <p><?php echo number_format($totalBorrowers); ?></p>
        </article>
        <article class="preview-card">
            <h2>Total loan amount</h2>
            <p>$<?php echo formatCurrency($totalLoanAmount); ?></p>
        </article>
        <article class="preview-card">
            <h2>Loans issued</h2>
            <p><?php echo number_format($totalLoans); ?></p>
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
            <span class="meta-label">Total borrowers</span>
            <span class="meta-value"><?php echo number_format($totalBorrowers); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Total loan amount</span>
            <span class="meta-value">$<?php echo formatCurrency($totalLoanAmount); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Total loans</span>
            <span class="meta-value"><?php echo number_format($totalLoans); ?></span>
        </div>
    </section>

    <div class="actions">
        <button type="button" id="export-csv">Export to CSV</button>
    </div>

    <?php if (count($rows) === 0): ?>
        <div class="empty-state">
            <p>No borrowers found for the selected period.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                <tr>
                    <th scope="col">Borrower UID</th>
                    <th scope="col">Name</th>
                    <th scope="col">Gender</th>
                    <th scope="col" class="text-nowrap">Date of birth</th>
                    <th scope="col" class="numeric">Annual income</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Block</th>
                    <th scope="col">Street</th>
                    <th scope="col" class="text-nowrap">Unit no.</th>
                    <th scope="col">Building</th>
                    <th scope="col">Postal code</th>
                    <th scope="col">Address</th>
                    <th scope="col" class="numeric">Loan count</th>
                    <th scope="col" class="numeric">Total loan amount</th>
                    <th scope="col" class="text-nowrap">First loan date</th>
                    <th scope="col" class="text-nowrap">Last loan date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo formatText($row['uid'] ?? null); ?></td>
                        <td><?php echo formatText($row['name'] ?? null); ?></td>
                        <td><?php echo formatText($row['gender'] ?? null); ?></td>
                        <td class="text-nowrap"><?php echo formatDateValue($row['dob'] ?? null); ?></td>
                        <td class="numeric">$<?php echo formatCurrency($row['annual_income'] ?? null); ?></td>
                        <td><?php echo formatText($row['email'] ?? null); ?></td>
                        <td><?php echo formatText($row['hand_phone'] ?? null); ?></td>
                        <td><?php echo formatText($row['blk'] ?? null); ?></td>
                        <td><?php echo formatText($row['street'] ?? null); ?></td>
                        <td class="text-nowrap"><?php echo formatText($row['unit'] ?? null); ?></td>
                        <td><?php echo formatText($row['building'] ?? null); ?></td>
                        <td><?php echo formatText($row['pincode'] ?? null); ?></td>
                        <td><?php echo formatText($row['address1'] ?? null); ?></td>
                        <td class="numeric"><?php echo formatNumber($row['loan_count'] ?? null); ?></td>
                        <td class="numeric">$<?php echo formatCurrency($row['total_loan_amount'] ?? null); ?></td>
                        <td class="text-nowrap"><?php echo formatDateValue($row['first_loan_date'] ?? null); ?></td>
                        <td class="text-nowrap"><?php echo formatDateValue($row['last_loan_date'] ?? null); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
