<?php

namespace Modules\LoanManagement\Support;

class LoanCollectionConstants
{
    public const STATUSES = [
        'draft' => 'Draft',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'active' => 'Active',
        'due_today' => 'Due Today',
        'partial_payment' => 'Partial Payment',
        'overdue' => 'Overdue',
        'ptp' => 'Promise To Pay',
        'broken_ptp' => 'Broken Promise',
        'field_visit_required' => 'Field Visit Required',
        'skip_customer' => 'Skip Customers',
        'delinquent' => 'Delinquent',
        'recovery' => 'Recovery Management',
        'debt_collection' => 'Debt Collection',
        'repossession' => 'Repossession',
        'legal' => 'Legal',
        'blacklisted' => 'Blacklisted',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
        'written_off' => 'Written Off',
    ];

    public const RISK_LEVELS = [
        'normal' => 'Normal',
        'low_risk' => 'Low Risk',
        'medium_risk' => 'Medium Risk',
        'high_risk' => 'High Risk',
        'critical' => 'Critical',
        'fraud_risk' => 'Fraud Risk',
        'soft_skip' => 'Soft Skip',
        'hard_skip' => 'Hard Skip',
    ];

    public const PTP_STATUSES = [
        'active' => 'Active',
        'fulfilled' => 'Fulfilled',
        'broken' => 'Broken',
        'expired' => 'Expired',
    ];

    public const VISIT_RESULTS = [
        'customer_met' => 'Customer Met',
        'customer_not_home' => 'Customer Not Home',
        'wrong_address' => 'Wrong Address',
        'refused_payment' => 'Refused Payment',
        'promised_payment' => 'Promised Payment',
        'property_verified' => 'Property Verified',
        'workplace_verified' => 'Workplace Verified',
        'neighbor_contacted' => 'Neighbor Contacted',
        'phone_unreachable' => 'Phone Unreachable',
        'legal_warning_given' => 'Legal Warning Given',
    ];

    public const OVERDUE_BUCKETS = [
        'current' => 'Current',
        '1_7' => '1-7 Days',
        '8_30' => '8-30 Days',
        '31_60' => '31-60 Days',
        '61_90' => '61-90 Days',
        '91_180' => '91-180 Days',
        '180_plus' => '180+ Days',
    ];

    public const KHMER = [
        'skip_customers' => 'អតិថិជនបាត់ការទំនាក់ទំនង',
        'unreachable_customers' => 'អតិថិជនមិនអាចទាក់ទងបាន',
        'promise_to_pay' => 'សន្យាបង់ប្រាក់',
        'field_visit_required' => 'ត្រូវចុះទៅទីតាំង',
        'debt_collection' => 'ការប្រមូលបំណុល',
        'recovery_management' => 'គ្រប់គ្រងការសងត្រឡប់',
        'legal_cases' => 'ករណីផ្លូវច្បាប់',
        'repossessions' => 'ការដកហូតទ្រព្យ',
        'fraud_risk' => 'ហានិភ័យបន្លំ',
    ];

    public const REPORTS = [
        'overdue-aging' => 'Overdue Aging Report',
        'skip-customers' => 'Skip Customer Report',
        'collector-performance' => 'Collector Performance',
        'recovery' => 'Recovery Report',
        'ptp-compliance' => 'PTP Compliance Report',
        'broken-promise' => 'Broken Promise Report',
        'legal-cases' => 'Legal Case Report',
        'repossession' => 'Repossession Report',
        'risk-analysis' => 'Risk Analysis Report',
    ];

    public static function badgeClass(?string $status, ?string $risk = null): string
    {
        $risk = strtolower((string) $risk);
        $status = strtolower((string) $status);

        if (in_array($risk, ['fraud_risk', 'critical'], true) || in_array($status, ['blacklisted'], true)) {
            return 'label label-default';
        }

        return match ($status) {
            'active', 'approved', 'closed' => 'label label-success',
            'due_today', 'ptp', 'pending', 'partial_payment' => 'label label-warning',
            'overdue', 'field_visit_required', 'recovery', 'repossession' => 'label label-warning',
            'broken_ptp', 'debt_collection', 'legal', 'delinquent' => 'label label-danger',
            default => 'label label-default',
        };
    }
}
