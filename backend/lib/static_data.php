<?php

declare(strict_types=1);

if (!function_exists('getStaticDisbursements')) {
    function getStaticDisbursements(): array
    {
        return [
            [
                'id' => 5001,
                'application_id' => 2001,
                'date' => '2024-03-05',
                'amount' => 5000.0,
                'account_number' => 'LN-2024-001',
                'remarks' => 'On track with repayments.',
                'status' => 'Active',
                'installment_count' => 12,
            ],
            [
                'id' => 5002,
                'application_id' => 2002,
                'date' => '2024-04-20',
                'amount' => 12000.0,
                'account_number' => 'LN-2024-002',
                'remarks' => 'Eligible for top up review.',
                'status' => 'Active',
                'installment_count' => 18,
            ],
            [
                'id' => 5003,
                'application_id' => 2003,
                'date' => '2024-05-12',
                'amount' => 8000.0,
                'account_number' => 'LN-2024-003',
                'remarks' => 'Watch late fees.',
                'status' => 'In arrears',
                'installment_count' => 15,
            ],
        ];
    }
}

if (!function_exists('getStaticRepayments')) {
    function getStaticRepayments(): array
    {
        return [
            [
                'id' => 8001,
                'disbursement_id' => 5001,
                'date' => '2024-04-04',
                'amount' => 2600.0,
                'principal' => 2500.0,
                'interest' => 80.0,
                'legal_fee' => 0.0,
                'acceptance_fee' => 20.0,
                'contract_variation_fee' => 0.0,
                'cheque_dishonoured_fee' => 0.0,
                'termination_fee' => 0.0,
                'renewal_fee' => 0.0,
                'late_fee' => 0.0,
                'cheque_dishonour' => '0',
                'deleted' => 0,
            ],
            [
                'id' => 8002,
                'disbursement_id' => 5001,
                'date' => '2024-05-04',
                'amount' => 2600.0,
                'principal' => 2500.0,
                'interest' => 80.0,
                'legal_fee' => 0.0,
                'acceptance_fee' => 0.0,
                'contract_variation_fee' => 0.0,
                'cheque_dishonoured_fee' => 0.0,
                'termination_fee' => 0.0,
                'renewal_fee' => 0.0,
                'late_fee' => 0.0,
                'cheque_dishonour' => '0',
                'deleted' => 0,
            ],
            [
                'id' => 8003,
                'disbursement_id' => 5002,
                'date' => '2024-05-19',
                'amount' => 6100.0,
                'principal' => 6000.0,
                'interest' => 90.0,
                'legal_fee' => 0.0,
                'acceptance_fee' => 10.0,
                'contract_variation_fee' => 0.0,
                'cheque_dishonoured_fee' => 0.0,
                'termination_fee' => 0.0,
                'renewal_fee' => 0.0,
                'late_fee' => 0.0,
                'cheque_dishonour' => '0',
                'deleted' => 0,
            ],
            [
                'id' => 8004,
                'disbursement_id' => 5003,
                'date' => '2024-06-10',
                'amount' => 0.0,
                'principal' => 0.0,
                'interest' => 0.0,
                'legal_fee' => 0.0,
                'acceptance_fee' => 0.0,
                'contract_variation_fee' => 0.0,
                'cheque_dishonoured_fee' => 0.0,
                'termination_fee' => 0.0,
                'renewal_fee' => 0.0,
                'late_fee' => 45.0,
                'cheque_dishonour' => '0',
                'deleted' => 0,
            ],
        ];
    }
}

if (!function_exists('getStaticPaymentSchedules')) {
    function getStaticPaymentSchedules(): array
    {
        return [
            [
                'id' => 7001,
                'disbursement_id' => 5001,
                'date' => '2024-04-05',
                'amount' => 2600.0,
                'principal' => 2440.0,
                'interest' => 160.0,
                'late_fee' => 0.0,
                'late_interest' => 0.0,
                'skip' => 0,
                'deleted' => 0,
            ],
            [
                'id' => 7002,
                'disbursement_id' => 5001,
                'date' => '2024-05-05',
                'amount' => 2600.0,
                'principal' => 2440.0,
                'interest' => 160.0,
                'late_fee' => 0.0,
                'late_interest' => 0.0,
                'skip' => 0,
                'deleted' => 0,
            ],
            [
                'id' => 7003,
                'disbursement_id' => 5002,
                'date' => '2024-05-20',
                'amount' => 6100.0,
                'principal' => 5860.0,
                'interest' => 240.0,
                'late_fee' => 0.0,
                'late_interest' => 0.0,
                'skip' => 0,
                'deleted' => 0,
            ],
            [
                'id' => 7004,
                'disbursement_id' => 5002,
                'date' => '2024-06-20',
                'amount' => 6100.0,
                'principal' => 5860.0,
                'interest' => 240.0,
                'late_fee' => 0.0,
                'late_interest' => 0.0,
                'skip' => 0,
                'deleted' => 0,
            ],
            [
                'id' => 7005,
                'disbursement_id' => 5003,
                'date' => '2024-06-12',
                'amount' => 3200.0,
                'principal' => 2990.0,
                'interest' => 210.0,
                'late_fee' => 0.0,
                'late_interest' => 0.0,
                'skip' => 0,
                'deleted' => 0,
            ],
        ];
    }
}

if (!function_exists('getStaticBorrowers')) {
    function getStaticBorrowers(): array
    {
        return [
            [
                'id' => 1001,
                'uid' => 'S9012345A',
                'name' => 'Alicia Tan',
                'gender' => 'Female',
                'dob' => '1990-04-14',
                'annual_income' => 62000.0,
                'blk' => '123',
                'street' => 'Serangoon Ave 3',
                'unit' => '05-12',
                'building' => 'Golden Court',
                'pincode' => '550123',
                'address1' => '123 Serangoon Ave 3',
                'email' => 'alicia.tan@example.com',
                'hand_phone' => '+65 8123 4567',
            ],
            [
                'id' => 1002,
                'uid' => 'S8912345B',
                'name' => 'Benjamin Singh',
                'gender' => 'Male',
                'dob' => '1989-08-02',
                'annual_income' => 78000.0,
                'blk' => '88',
                'street' => 'Bedok North St 4',
                'unit' => '12-21',
                'building' => 'Vista Towers',
                'pincode' => '460088',
                'address1' => '88 Bedok North St 4',
                'email' => 'ben.singh@example.com',
                'hand_phone' => '+65 8234 5678',
            ],
            [
                'id' => 1003,
                'uid' => 'S9001234C',
                'name' => 'Celine Koh',
                'gender' => 'Female',
                'dob' => '1992-01-26',
                'annual_income' => 54000.0,
                'blk' => '18',
                'street' => 'Jurong West Ave 1',
                'unit' => '03-08',
                'building' => 'Lakefront Residences',
                'pincode' => '640018',
                'address1' => '18 Jurong West Ave 1',
                'email' => 'celine.koh@example.com',
                'hand_phone' => '+65 8345 6789',
            ],
        ];
    }
}

if (!function_exists('getStaticApplications')) {
    function getStaticApplications(): array
    {
        return [
            ['id' => 2001, 'borrower_id' => 1001, 'date' => '2024-03-01', 'amount' => 5000.0, 'deleted' => 0, 'account_number' => 'APP-2024-001'],
            ['id' => 2002, 'borrower_id' => 1002, 'date' => '2024-04-15', 'amount' => 12000.0, 'deleted' => 0, 'account_number' => 'APP-2024-002'],
            ['id' => 2003, 'borrower_id' => 1003, 'date' => '2024-05-08', 'amount' => 8000.0, 'deleted' => 0, 'account_number' => 'APP-2024-003'],
        ];
    }
}

if (!function_exists('getStaticBorrowerLoanSummaries')) {
    function getStaticBorrowerLoanSummaries(string $startDate, string $endDate): array
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

        $summaries = [];

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
            $borrower = $borrowerIndex[$borrowerId] ?? null;
            if ($borrower === null) {
                continue;
            }

            if (!isset($summaries[$borrowerId])) {
                $summaries[$borrowerId] = [
                    'borrower_id' => $borrowerId,
                    'uid' => $borrower['uid'] ?? null,
                    'name' => $borrower['name'] ?? null,
                    'gender' => $borrower['gender'] ?? null,
                    'dob' => $borrower['dob'] ?? null,
                    'annual_income' => isset($borrower['annual_income']) ? (float) $borrower['annual_income'] : null,
                    'blk' => $borrower['blk'] ?? null,
                    'street' => $borrower['street'] ?? null,
                    'unit' => $borrower['unit'] ?? null,
                    'building' => $borrower['building'] ?? null,
                    'pincode' => $borrower['pincode'] ?? null,
                    'address1' => $borrower['address1'] ?? null,
                    'email' => $borrower['email'] ?? null,
                    'hand_phone' => $borrower['hand_phone'] ?? null,
                    'loan_count' => 0,
                    'total_loan_amount' => 0.0,
                    'first_loan_date' => null,
                    'last_loan_date' => null,
                ];
            }

            $summary = $summaries[$borrowerId];
            $summary['loan_count']++;
            $summary['total_loan_amount'] += (float) ($disbursement['amount'] ?? 0.0);

            $loanDate = $disbursement['date'] ?? null;
            if ($loanDate !== null) {
                if ($summary['first_loan_date'] === null || $loanDate < $summary['first_loan_date']) {
                    $summary['first_loan_date'] = $loanDate;
                }

                if ($summary['last_loan_date'] === null || $loanDate > $summary['last_loan_date']) {
                    $summary['last_loan_date'] = $loanDate;
                }
            }

            $summaries[$borrowerId] = $summary;
        }

        $results = array_values($summaries);
        usort(
            $results,
            static function (array $left, array $right): int {
                $nameComparison = strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
                if ($nameComparison !== 0) {
                    return $nameComparison;
                }

                return ($left['borrower_id'] ?? 0) <=> ($right['borrower_id'] ?? 0);
            }
        );

        return $results;
    }
}
