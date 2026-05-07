<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The 15 itemised expense buckets HMRC accepts on a quarterly self-employment
 * update. The 16th option is the consolidated_expenses single-field
 * alternative — mutually exclusive with these. Each case's value is the DB
 * column suffix (snake_case); HMRC's API uses camelCase, mapped at payload time.
 */
enum ItsaExpenseCategory: string
{
    case CostOfGoods = 'cost_of_goods';
    case PaymentsToSubcontractors = 'payments_to_subcontractors';
    case WagesAndStaffCosts = 'wages_and_staff_costs';
    case CarVanTravelExpenses = 'car_van_travel_expenses';
    case PremisesRunningCosts = 'premises_running_costs';
    case MaintenanceCosts = 'maintenance_costs';
    case AdminCosts = 'admin_costs';
    case BusinessEntertainmentCosts = 'business_entertainment_costs';
    case AdvertisingCosts = 'advertising_costs';
    case InterestOnBankOtherLoans = 'interest_on_bank_other_loans';
    case FinanceCharges = 'finance_charges';
    case IrrecoverableDebts = 'irrecoverable_debts';
    case ProfessionalFees = 'professional_fees';
    case Depreciation = 'depreciation';
    case OtherExpenses = 'other_expenses';

    public function label(): string
    {
        return match ($this) {
            self::CostOfGoods => 'Cost of goods',
            self::PaymentsToSubcontractors => 'Payments to subcontractors',
            self::WagesAndStaffCosts => 'Wages and staff costs',
            self::CarVanTravelExpenses => 'Car / van / travel',
            self::PremisesRunningCosts => 'Premises running costs',
            self::MaintenanceCosts => 'Maintenance',
            self::AdminCosts => 'Admin costs',
            self::BusinessEntertainmentCosts => 'Business entertainment',
            self::AdvertisingCosts => 'Advertising',
            self::InterestOnBankOtherLoans => 'Interest on bank / other loans',
            self::FinanceCharges => 'Finance charges',
            self::IrrecoverableDebts => 'Irrecoverable debts',
            self::ProfessionalFees => 'Professional fees',
            self::Depreciation => 'Depreciation',
            self::OtherExpenses => 'Other expenses',
        };
    }

    /**
     * The corresponding camelCase key in HMRC's API payload.
     */
    public function hmrcKey(): string
    {
        return match ($this) {
            self::CostOfGoods => 'costOfGoods',
            self::PaymentsToSubcontractors => 'paymentsToSubcontractors',
            self::WagesAndStaffCosts => 'wagesAndStaffCosts',
            self::CarVanTravelExpenses => 'carVanTravelExpenses',
            self::PremisesRunningCosts => 'premisesRunningCosts',
            self::MaintenanceCosts => 'maintenanceCosts',
            self::AdminCosts => 'adminCosts',
            self::BusinessEntertainmentCosts => 'businessEntertainmentCosts',
            self::AdvertisingCosts => 'advertisingCosts',
            self::InterestOnBankOtherLoans => 'interestOnBankOtherLoans',
            self::FinanceCharges => 'financeCharges',
            self::IrrecoverableDebts => 'irrecoverableDebts',
            self::ProfessionalFees => 'professionalFees',
            self::Depreciation => 'depreciation',
            self::OtherExpenses => 'otherExpenses',
        };
    }

    /**
     * The DB column name backing this category on `hmrc_itsa_quarterly_updates`.
     */
    public function column(): string
    {
        return $this->value.'_pence';
    }
}
